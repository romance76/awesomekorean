<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 오픈 전 가상(더미) 데이터 정리 — 가상 회원을 100명으로 줄이고, 글·댓글·가입 날짜를 9월 이후로 바꾼다.
 * 기본은 "사전 점검"(아무것도 바꾸지 않고 숫자만 보여 줌). 실제로 바꾸려면 --execute.
 *
 * 남길 100명: database/data/dummy_members_100.json (이름 뒤 숫자를 뗀 이름, 합쳐질 계정 목록 포함)
 * 순서: ① 합쳐질 계정의 글을 대표 계정으로 옮김 ② 지워질 회원의 글을 100명에게 나눠 옮김 ③ Q&A 를 약 1,000개로 줄임
 *       ④ 날짜 바꾸기 ⑤ 이름 바꾸기 ⑥ 나머지 가상 회원 삭제 ⑦ 숫자(댓글 수 등) 다시 세기
 * 안전장치: 관리자·슈퍼관리자와 @test.com/@awesomekorean.local 이 아닌 계정은 절대 건드리지 않고,
 *           결제·광고·경품 기록이 있는 가상 회원은 지우지 않는다(목록으로 알려 줌).
 */
class DummyCleanup extends Command
{
    protected $signature = 'dummy:cleanup {--execute : 실제로 바꾸기(없으면 사전 점검만)} {--qa-keep=1000 : 남길 Q&A 질문 수} {--from=2026-09-08 : 글·댓글 날짜 시작(애틀랜타)} {--to=2026-10-13 : 글·댓글 날짜 끝}';
    protected $description = '가상 회원 100명으로 줄이기 + 글 날짜 9월 이후로 바꾸기 (기본: 사전 점검만)';

    /** 날짜를 건드리는 표 (작성자 칸 기준) */
    private const PROTECT_TABLES = ['payments', 'invoices', 'banner_ads', 'flyer_ads', 'sweepstakes_entries', 'sweepstakes_winners', 'sweepstakes_prize_claims', 'job_promotions', 'post_promotions', 'event_attendees'];

    private bool $run = false;
    private Carbon $from;
    private Carbon $to;

    public function handle(): int
    {
        $this->run = (bool) $this->option('execute');
        mt_srand(20261014);
        $this->from = Carbon::parse($this->option('from') . ' 00:00:00', 'America/New_York')->utc();
        $this->to = Carbon::parse($this->option('to') . ' 23:00:00', 'America/New_York')->utc();

        $sel = json_decode((string) file_get_contents(database_path('data/dummy_members_100.json')), true);
        if (!is_array($sel) || count($sel) !== 100) { $this->error('남길 회원 명단(100명)을 읽지 못했어요'); return self::FAILURE; }

        // ── 대상 구분 ──
        $dummy = DB::table('users')->where(fn ($q) => $q->where('email', 'like', '%@test.com')->orWhere('email', 'like', '%@awesomekorean.local'))
            ->whereNotIn('role', ['admin', 'super_admin'])->pluck('id')->all();
        $dummySet = array_flip($dummy);
        $rep = []; $mergeMap = []; $keep = []; $keepGroup = [];
        foreach ($sel as $s) {
            foreach ($s['ids'] as $id) if (!isset($dummySet[$id])) { $this->error("가상 회원이 아닌 계정이 명단에 있어요: #{$id}"); return self::FAILURE; }
            $keep[] = $s['rep']; $keepGroup[$s['rep']] = $s['group'];
            foreach ($s['ids'] as $id) $mergeMap[$id] = $s['rep'];
        }
        $keepSet = array_flip($keep);

        // 결제·광고·경품 기록이 있는 가상 회원은 지우지 않는다
        $protected = [];
        foreach (self::PROTECT_TABLES as $t) {
            if (!\Illuminate\Support\Facades\Schema::hasTable($t) || !\Illuminate\Support\Facades\Schema::hasColumn($t, 'user_id')) continue;
            foreach (DB::table($t)->whereIn('user_id', $dummy)->distinct()->pluck('user_id') as $uid) $protected[(int) $uid][] = $t;
        }
        $mergedAway = array_values(array_filter(array_keys($mergeMap), fn ($id) => $mergeMap[$id] !== $id && !isset($protected[$id])));
        $removable = array_values(array_filter($dummy, fn ($id) => !isset($keepSet[$id]) && !isset($protected[$id])));
        $keptProtected = array_values(array_filter(array_keys($protected), fn ($id) => !isset($keepSet[$id])));

        $this->info(($this->run ? '【실제 실행】' : '【사전 점검 — 아무것도 바꾸지 않아요】'));
        $this->line('가상 회원 전체 ' . count($dummy) . '명 → 남김 ' . count($keep) . '명 (합쳐지는 계정 ' . count($mergedAway) . '개 포함)');
        $this->line('지워질 회원 ' . count($removable) . '명 · 결제/광고/경품 기록이 있어 그대로 두는 회원 ' . count($keptProtected) . '명' . ($keptProtected ? ' → #' . implode(', #', array_slice($keptProtected, 0, 15)) : ''));

        // ── 옮길 글 수 ──
        $authored = ['posts', 'comments', 'job_posts', 'group_buys', 'chat_messages', 'qa_posts', 'qa_answers'];
        $rows = [];
        foreach ($authored as $t) {
            $rows[$t] = ['merge' => DB::table($t)->whereIn('user_id', $mergedAway)->count(), 'move' => DB::table($t)->whereIn('user_id', $removable)->count()];
            $this->line(sprintf('  %-14s 합치기로 옮김 %5d · 지워질 회원 글 옮김 %5d', $t, $rows[$t]['merge'], $rows[$t]['move']));
        }

        // ── Q&A 줄이기 ──
        $keepQa = max(100, (int) $this->option('qa-keep'));
        $withAns = DB::table('qa_posts')->whereExists(fn ($q) => $q->selectRaw(1)->from('qa_answers')->whereColumn('qa_answers.qa_post_id', 'qa_posts.id'))->pluck('id')->all();
        shuffle($withAns);
        $qaKeepIds = array_slice($withAns, 0, $keepQa);
        $answersKept = DB::table('qa_answers')->whereIn('qa_post_id', $qaKeepIds)->count();
        $this->line('Q&A 질문 ' . DB::table('qa_posts')->count() . ' → ' . count($qaKeepIds) . '개 · 답변 ' . DB::table('qa_answers')->count() . ' → ' . $answersKept . '개');

        // ── 지울 때 막힐 수 있는 연결 확인 ──
        $blockers = DB::select("SELECT k.TABLE_NAME t, k.COLUMN_NAME c FROM information_schema.REFERENTIAL_CONSTRAINTS rc JOIN information_schema.KEY_COLUMN_USAGE k ON k.CONSTRAINT_NAME = rc.CONSTRAINT_NAME AND k.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA WHERE rc.CONSTRAINT_SCHEMA = DATABASE() AND rc.REFERENCED_TABLE_NAME = 'users' AND rc.DELETE_RULE IN ('NO ACTION','RESTRICT')");
        foreach ($blockers as $b) {
            $n = DB::table($b->t)->whereIn($b->c, array_merge($mergedAway, $removable))->count();
            if ($n) $this->warn("  ⚠ {$b->t}.{$b->c} 에 지울 회원의 기록 {$n}건 — 지우기가 막힐 수 있어요");
        }

        // ── 날짜 ──
        $this->line('날짜: 회원 가입 9/1~9/7, 글·댓글 ' . $this->from->copy()->timezone('America/New_York')->format('n/j') . '~' . $this->to->copy()->timezone('America/New_York')->format('n/j') . ' (애틀랜타) 무작위');
        $this->line('이름 바꾸기 예: ' . implode(' / ', array_map(fn ($s) => $s['final'], array_slice($sel, 0, 6))) . ' …');

        if (!$this->run) { $this->info('→ 실제로 바꾸려면: php artisan dummy:cleanup --execute (먼저 백업!)'); return self::SUCCESS; }

        DB::transaction(function () use ($sel, $mergeMap, $keep, $keepGroup, $mergedAway, $removable, $qaKeepIds, $authored) {
            $testKeep = array_values(array_filter($keep, fn ($id) => $keepGroup[$id] === 'test'));
            $pick = fn (array $pool) => $pool[mt_rand(0, count($pool) - 1)];

            // ① 합쳐질 계정의 글 → 대표 계정
            foreach ($mergeMap as $from => $to) {
                if ($from === $to) continue;
                foreach ($authored as $t) DB::table($t)->where('user_id', $from)->update(['user_id' => $to]);
            }
            // ③ Q&A 줄이기 (먼저 지우고 나머지만 옮김)
            DB::table('qa_answers')->whereNotIn('qa_post_id', $qaKeepIds)->delete();
            DB::table('qa_posts')->whereNotIn('id', $qaKeepIds)->delete();
            DB::table('qa_posts')->whereNotNull('best_answer_id')->whereNotIn('best_answer_id', DB::table('qa_answers')->select('id'))->update(['best_answer_id' => null]);

            // ② 지워질 회원의 글 → 남는 회원에게 나눠 옮김 (Q&A 는 100명 전체, 나머지는 직접 만든 회원 쪽)
            foreach (['posts', 'comments', 'job_posts', 'group_buys', 'chat_messages'] as $t) {
                foreach (DB::table($t)->whereIn('user_id', $removable)->pluck('id', 'id') as $id) DB::table($t)->where('id', $id)->update(['user_id' => $pick($testKeep)]);
            }
            foreach (DB::table('qa_posts')->whereIn('user_id', $removable)->pluck('id') as $id) DB::table('qa_posts')->where('id', $id)->update(['user_id' => $pick($keep)]);
            $qUser = DB::table('qa_posts')->pluck('user_id', 'id')->all();
            foreach (DB::table('qa_answers')->whereIn('user_id', $removable)->get(['id', 'qa_post_id']) as $a) {
                do { $u = $pick($keep); } while (($qUser[$a->qa_post_id] ?? 0) === $u);
                DB::table('qa_answers')->where('id', $a->id)->update(['user_id' => $u]);
            }

            // ⑥ 지우기 (합쳐진 계정 + 지워질 회원) — 남은 연결 기록은 자동으로 같이 지워짐
            foreach (array_chunk(array_merge($mergedAway, $removable), 300) as $chunk) DB::table('users')->whereIn('id', $chunk)->delete();

            // ⑤ 이름 바꾸기 (+ 합쳐진 계정 중 사업자가 있으면 사업자 권한)
            foreach ($sel as $s) {
                $upd = ['nickname' => $s['final']];
                if ($s['group'] === 'local') $upd['name'] = $s['final'];
                if (in_array('business', $s['roles'], true)) $upd['role'] = 'business';
                DB::table('users')->where('id', $s['rep'])->update($upd);
            }

            // ④ 날짜
            $rand = fn (Carbon $a, Carbon $b) => $a->copy()->addSeconds(mt_rand(0, max(1, $b->getTimestamp() - $a->getTimestamp())));
            $joinA = Carbon::parse('2026-09-01 09:00:00', 'America/New_York')->utc(); $joinB = Carbon::parse('2026-09-07 21:00:00', 'America/New_York')->utc();
            foreach ($keep as $id) {
                $c = $rand($joinA, $joinB);
                DB::table('users')->where('id', $id)->update(['created_at' => $c, 'updated_at' => $c, 'last_login_at' => $rand($this->from, $this->to), 'last_active_at' => $rand($this->from, $this->to)]);
            }
            $keepSet = array_flip($keep);
            foreach (['posts', 'job_posts', 'group_buys'] as $t) {
                foreach (DB::table($t)->whereIn('user_id', $keep)->pluck('id') as $id) { $d = $rand($this->from, $this->to); DB::table($t)->where('id', $id)->update(['created_at' => $d, 'updated_at' => $d]); }
            }
            // 질문 → 답변
            foreach (DB::table('qa_posts')->pluck('id') as $qid) {
                $d = $rand($this->from, $this->to->copy()->subDays(1)); DB::table('qa_posts')->where('id', $qid)->update(['created_at' => $d, 'updated_at' => $d]);
                foreach (DB::table('qa_answers')->where('qa_post_id', $qid)->orderBy('id')->pluck('id') as $aid) {
                    $d = $rand($d->copy()->addMinutes(20), $d->copy()->addDays(6)); if ($d->gt($this->to)) $d = $this->to->copy()->subMinutes(mt_rand(1, 600));
                    DB::table('qa_answers')->where('id', $aid)->update(['created_at' => $d, 'updated_at' => $d]);
                }
            }
            // 댓글(글·구인·공동구매에 달린 것 중 남은 회원이 쓴 것): 부모 글 날짜 이후, 답글은 부모 댓글 이후
            $parents = ['App\\Models\\Post' => 'posts', 'App\\Models\\JobPost' => 'job_posts', 'App\\Models\\GroupBuy' => 'group_buys'];
            $cdate = [];
            foreach (DB::table('comments')->whereIn('commentable_type', array_keys($parents))->orderBy('id')->get(['id', 'commentable_type', 'commentable_id', 'parent_id', 'user_id', 'created_at']) as $c) {
                if (!isset($keepSet[$c->user_id])) { $cdate[$c->id] = Carbon::parse($c->created_at); continue; }   // 진짜 회원 댓글은 그대로
                $pt = DB::table($parents[$c->commentable_type])->where('id', $c->commentable_id)->value('created_at');
                $base = $c->parent_id && isset($cdate[$c->parent_id]) ? $cdate[$c->parent_id] : ($pt ? Carbon::parse($pt) : $this->from);
                $d = $rand($base->copy()->addMinutes(8), $base->copy()->addDays(8)); if ($d->gt($this->to)) $d = $this->to->copy()->subMinutes(mt_rand(1, 900));
                if ($d->lt($base)) $d = $base->copy()->addMinutes(5);
                $cdate[$c->id] = $d;
                DB::table('comments')->where('id', $c->id)->update(['created_at' => $d, 'updated_at' => $d]);
            }

            // ⑦ 숫자 다시 세기
            DB::statement("UPDATE posts p SET comment_count = (SELECT COUNT(*) FROM comments c WHERE c.commentable_type = 'App\\\\Models\\\\Post' AND c.commentable_id = p.id AND c.is_hidden = 0)");
            DB::statement('UPDATE qa_posts q SET answer_count = (SELECT COUNT(*) FROM qa_answers a WHERE a.qa_post_id = q.id)');
            DB::statement('UPDATE group_buys g SET current_participants = (SELECT COUNT(*) FROM group_buy_participants x WHERE x.group_buy_id = g.id)');
        });

        $this->info('끝났어요. 가상 회원 ' . DB::table('users')->where(fn ($q) => $q->where('email', 'like', '%@test.com')->orWhere('email', 'like', '%@awesomekorean.local'))->whereNotIn('role', ['admin', 'super_admin'])->count() . '명 남음');
        return self::SUCCESS;
    }
}
