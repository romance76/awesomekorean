<?php

namespace App\Support;

use App\Events\NewNotification;
use App\Models\Event;
use App\Models\Notification;
use App\Models\Sweepstakes;
use App\Models\SweepstakesPrizeClaim;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 경품 자동화 — 반복 일정(이벤트 자동 생성) + 가입 보너스(매 N번째 가입).
 * 모든 시간은 DB 에 UTC 로 저장하고, 일정 계산만 애틀랜타 시각(America/New_York) 기준으로 한다.
 */
class SweepstakesAutomation
{
    private const TZ = 'America/New_York';

    // ───────────────────────── 반복 일정 ─────────────────────────

    /** 첫 실행 시각(애틀랜타) 이후 규칙에 맞는 다음 시각 (UTC Carbon) */
    public static function nextRunAfter(object $sch, ?Carbon $after = null): Carbon
    {
        [$h, $m] = array_map('intval', explode(':', $sch->start_time ?: '09:00') + [0, 0]);
        $base = ($after ? $after->copy() : now())->setTimezone(self::TZ);
        $c = $base->copy()->setTime($h, $m, 0);
        if ($sch->repeat_unit === 'daily') {
            if ($c->lte($base)) $c->addDay();
        } elseif ($sch->repeat_unit === 'monthly') {
            $d = max(1, min(28, (int) ($sch->month_day ?: 1)));
            $c = $base->copy()->day($d)->setTime($h, $m, 0);
            if ($c->lte($base)) $c = $base->copy()->startOfMonth()->addMonthNoOverflow()->day($d)->setTime($h, $m, 0);
        } else { // weekly
            $wd = (int) ($sch->weekday ?? 1);
            $c = $base->copy()->setTime($h, $m, 0);
            $diff = ($wd - (int) $c->dayOfWeek + 7) % 7;
            $c->addDays($diff);
            if ($c->lte($base)) $c->addWeek();
        }
        return $c->utc();
    }

    private static function render(string $tpl, int $n, Carbon $startNy): string
    {
        return str_replace(['{n}', '{date}'], [(string) $n, $startNy->format('n/j')], $tpl);
    }

    /** 일정에서 이번 회차 이벤트(+경품 추첨)를 만든다. 중복 회차는 unique 로 막는다. 생성된 Sweepstakes 반환 */
    public static function createRun(int $scheduleId, ?Carbon $slotUtc = null): ?Sweepstakes
    {
        return DB::transaction(function () use ($scheduleId, $slotUtc) {
            $sch = DB::table('sweepstakes_schedules')->where('id', $scheduleId)->lockForUpdate()->first();
            if (!$sch) return null;
            if ($sch->total_runs !== null && $sch->runs_done >= $sch->total_runs) {
                DB::table('sweepstakes_schedules')->where('id', $sch->id)->update(['status' => 'completed', 'next_run_at' => null, 'updated_at' => now()]);
                return null;
            }
            $slot = ($slotUtc ?: ($sch->next_run_at ? Carbon::parse($sch->next_run_at, 'UTC') : now()))->copy()->utc();
            $startNy = $slot->copy()->setTimezone(self::TZ);
            $n = (int) $sch->runs_done + 1;
            $endAt = $slot->copy()->addHours(max(1, (int) $sch->duration_hours));
            $title = self::render($sch->title_template, $n, $startNy);

            $event = Event::create([
                'user_id' => $sch->created_by ?: 1,
                'title' => mb_substr($title, 0, 200),
                'description' => $sch->description,
                'category' => 'awesomekorean', 'organizer' => '어썸코리안',
                'start_date' => $slot, 'end_date' => $endAt,
                'price' => 0, 'is_free' => true, 'is_active' => true, 'is_online' => true,
                'max_attendees' => 0, 'reward_points' => 0,
                'event_type' => 'sweepstakes',
                'image_url' => $sch->prize_image,
            ]);
            $sw = Sweepstakes::create([
                'event_id' => $event->id, 'title' => $event->title, 'description' => $sch->description,
                'prize_name' => $sch->prize_name, 'prize_value' => $sch->prize_value, 'prize_image' => $sch->prize_image,
                'draw_style' => 'lottery3d', 'start_at' => $slot, 'end_at' => $endAt, 'status' => 'active',
                'minimum_age' => 18, 'official_rules_url' => '/sweepstakes/rules',
                'winner_count' => max(1, (int) $sch->winner_count),
            ]);
            // 일정 연결 칸은 대량 할당 대상이 아니라 따로 저장
            DB::table('sweepstakes')->where('id', $sw->id)->update(['schedule_id' => $sch->id, 'run_no' => $n]);

            $done = $n;
            $finished = $sch->total_runs !== null && $done >= $sch->total_runs;
            DB::table('sweepstakes_schedules')->where('id', $sch->id)->update([
                'runs_done' => $done, 'last_run_at' => now(),
                'next_run_at' => $finished ? null : self::nextRunAfter($sch, $slot)->toDateTimeString(),
                'status' => $finished ? 'completed' : $sch->status,
                'updated_at' => now(),
            ]);
            return $sw;
        });
    }

    /** 스케줄러: 실행할 때가 된 일정마다 이벤트를 만든다 (놓친 회차는 몰아서 만들지 않고 한 번만) */
    public static function runDueSchedules(bool $dry = false): int
    {
        if (!Schema::hasTable('sweepstakes_schedules')) return 0;
        $created = 0;
        $due = DB::table('sweepstakes_schedules')->where('status', 'active')->whereNotNull('next_run_at')->where('next_run_at', '<=', now())->pluck('id');
        foreach ($due as $id) {
            if ($dry) { $created++; continue; }
            try {
                $row = DB::table('sweepstakes_schedules')->where('id', $id)->first();
                $slot = Carbon::parse($row->next_run_at, 'UTC');
                // 서버가 오래 멈춰 있었다면 지나간 시각이 아니라 지금부터 시작
                if ($slot->lt(now()->subHours(6))) $slot = now();
                if (self::createRun((int) $id, $slot)) $created++;
            } catch (\Throwable $e) {
                report($e);
            }
        }
        return $created;
    }

    /** 자동 추첨 일정으로 만든 이벤트가 끝났으면 당첨자 선정 (참가자가 있을 때만) */
    public static function autoDraw(): int
    {
        if (!Schema::hasTable('sweepstakes_schedules') || !Schema::hasColumn('sweepstakes', 'schedule_id')) return 0;
        $n = 0;
        $rows = DB::table('sweepstakes as s')->join('sweepstakes_schedules as c', 'c.id', '=', 's.schedule_id')
            ->where('c.auto_draw', true)->whereIn('s.status', ['active', 'ended'])->where('s.end_at', '<=', now())
            ->where('s.total_entries', '>', 0)->pluck('s.id');
        foreach ($rows as $id) {
            try {
                $s = Sweepstakes::find($id);
                if (!$s) continue;
                if ((int) ($s->winner_count ?? 1) > 1) SweepstakesWinnerService::selectWinners($s);
                else SweepstakesWinnerService::selectWinner($s);
                $n++;
            } catch (\Throwable $e) {
                report($e);
            }
        }
        return $n;
    }

    // ───────────────────────── 가입 보너스 ─────────────────────────

    /**
     * 회원이 "이메일 인증을 마친 순간" 호출한다 (이메일 가입은 인증 완료 시, 구글·아마존 가입은 가입과 동시에 인증된 상태로 만들어지므로 그때).
     * 켠 뒤로 인증을 마친 회원 수를 전용 카운터로 세어 N번째마다 당첨 — 전체 회원 수·더미·가져오기 계정과 무관하다.
     * 가입 자체는 절대 막지 않도록 호출하는 쪽에서 try/catch 한다.
     */
    public static function onSignup(int $userId): void
    {
        if (!Schema::hasTable('sweepstakes_milestones') || !Schema::hasColumn('sweepstakes_milestones', 'signup_counter')) return;
        $ids = DB::table('sweepstakes_milestones')->where('status', 'active')->where('trigger_type', 'nth_signup')->pluck('id');
        if ($ids->isEmpty()) return;
        foreach ($ids as $mid) {
            try {
                DB::transaction(function () use ($mid, $userId) {
                    $row = DB::table('sweepstakes_milestones')->where('id', $mid)->lockForUpdate()->first();
                    if (!$row || $row->status !== 'active') return;
                    $n = max(1, (int) $row->every_n);
                    $seq = (int) $row->signup_counter + 1;                       // 켠 뒤 몇 번째 인증 완료 회원인지
                    DB::table('sweepstakes_milestones')->where('id', $row->id)->update(['signup_counter' => $seq]);
                    if ($seq < $n || $seq % $n !== 0) return;
                    $no = intdiv($seq, $n);
                    if ($row->max_awards !== null && $row->awards_done >= $row->max_awards) {
                        DB::table('sweepstakes_milestones')->where('id', $row->id)->update(['status' => 'stopped', 'updated_at' => now()]);
                        return;
                    }
                    // 같은 구간 중복 지급 방지: unique(milestone_id, milestone_no) 에 걸리면 이미 처리된 것
                    $ins = DB::table('sweepstakes_milestone_awards')->insertOrIgnore([
                        'milestone_id' => $row->id, 'milestone_no' => $no, 'user_id' => $userId, 'member_count' => $seq, 'created_at' => now(),
                    ]);
                    if (!$ins) return;
                    $container = self::ensureContainer($row);
                    $claim = SweepstakesPrizeClaim::firstOrCreate(
                        ['sweepstakes_id' => $container, 'user_id' => $userId],
                        ['rank' => 1, 'prize_label' => $row->prize_name, 'notified_at' => now()]
                    );
                    DB::table('sweepstakes_prize_claims')->where('id', $claim->id)->update([
                        'prize_type' => $row->prize_type ?: 'digital', 'delivery_status' => 'pending', 'cost_usd' => $row->prize_value,
                    ]);
                    DB::table('sweepstakes_milestones')->where('id', $row->id)->update(['awards_done' => DB::raw('awards_done + 1'), 'updated_at' => now()]);
                    DB::table('sweepstakes_delivery_logs')->insert([
                        'claim_id' => $claim->id, 'sweepstakes_id' => $container, 'user_id' => $userId, 'actor_id' => null,
                        'action' => 'milestone_awarded', 'note' => "{$row->name} — {$seq}번째 가입", 'meta' => json_encode(['milestone_id' => $row->id, 'signup_no' => $seq]), 'created_at' => now(),
                    ]);
                    DB::afterCommit(fn () => self::notifyMilestoneWinner($userId, $row, $seq));
                });
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /** 가입 보너스 당첨자를 모아 두는 sweepstakes 행(종료·당첨 상태) — 지급 관리 화면을 그대로 쓰기 위한 그릇 */
    private static function ensureContainer(object $m): int
    {
        if ($m->container_id && DB::table('sweepstakes')->where('id', $m->container_id)->exists()) return (int) $m->container_id;
        $id = DB::table('sweepstakes')->insertGetId([
            'event_id' => null, 'title' => '🎯 가입 보너스 · ' . $m->name, 'description' => '가입 순번 이벤트 당첨자 모음',
            'prize_name' => $m->prize_name, 'prize_value' => $m->prize_value, 'draw_style' => 'lottery3d',
            'start_at' => now(), 'end_at' => now(), 'status' => 'winner_selected', 'kind' => 'milestone',
            'minimum_age' => 0, 'winner_count' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sweepstakes_milestones')->where('id', $m->id)->update(['container_id' => $id]);
        return $id;
    }

    private static function notifyMilestoneWinner(int $userId, object $m, int $count): void
    {
        try {
            $title = '🎉 가입 보너스에 당첨됐어요!';
            Notification::create([
                'user_id' => $userId, 'type' => 'sweepstakes_win', 'title' => $title,
                'content' => "{$count}번째 가입 회원이 되셨어요! {$m->prize_name} 상품은 곧 쪽지로 보내 드려요.",
                'data' => ['url' => '/dashboard'],
            ]);
            $unread = Notification::where('user_id', $userId)->whereNull('read_at')->count();
            broadcast(new NewNotification($userId, $unread, $title));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
