<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\QaPost;
use App\Models\QaAnswer;
use Carbon\Carbon;

/**
 * Q&A 글의 created_at/updated_at 을 최근 N개월 (기본 6) 사이로 랜덤 분포.
 * - 답변은 부모 질문 작성일 이후 ~ 현재 사이로 랜덤
 * - 댓글(commentable=QaPost/QaAnswer) 도 함께 보정
 *
 * 사용:
 *   php artisan qa:randomize-dates
 *   php artisan qa:randomize-dates --months=6
 *   php artisan qa:randomize-dates --dry
 */
class RandomizeQaDates extends Command
{
    protected $signature = 'qa:randomize-dates {--months=6 : 분포할 기간 (개월)} {--dry : 실제 업데이트 안 함}';
    protected $description = 'Q&A 질문/답변 날짜를 최근 N개월 사이로 랜덤 분포';

    public function handle(): int
    {
        $months = (int) $this->option('months');
        $dry = (bool) $this->option('dry');

        $now = Carbon::now();
        $cutoff = $now->copy()->subMonths($months);
        $totalSec = abs($now->diffInSeconds($cutoff));

        $this->info("📅 분포 범위: {$cutoff->format('Y-m-d')} ~ {$now->format('Y-m-d')} ({$months} 개월)");
        if ($dry) $this->warn('🔍 DRY RUN — 실제 업데이트 안 함');

        // ─── 1) 질문 ───
        $posts = QaPost::select('id')->get();
        $this->info('📝 질문 ' . $posts->count() . '개');
        $bar = $this->output->createProgressBar($posts->count());
        $bar->start();

        $postDates = []; // id => Carbon
        foreach ($posts as $p) {
            $randSec = mt_rand(0, $totalSec);
            $d = $cutoff->copy()->addSeconds($randSec);
            $postDates[$p->id] = $d;
            if (!$dry) {
                DB::table('qa_posts')->where('id', $p->id)->update([
                    'created_at' => $d,
                    'updated_at' => $d,
                ]);
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        // ─── 2) 답변 (부모 질문일 ~ 현재) ───
        $answers = QaAnswer::select('id', 'qa_post_id')->get();
        $this->info('💬 답변 ' . $answers->count() . '개');
        $bar = $this->output->createProgressBar($answers->count());
        $bar->start();

        foreach ($answers as $a) {
            $parentDate = $postDates[$a->qa_post_id] ?? $cutoff;
            $afterParentSec = max(60, abs($now->diffInSeconds($parentDate)));
            $randSec = mt_rand(0, $afterParentSec);
            $d = $parentDate->copy()->addSeconds($randSec);
            if (!$dry) {
                DB::table('qa_answers')->where('id', $a->id)->update([
                    'created_at' => $d,
                    'updated_at' => $d,
                ]);
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        // ─── 3) 댓글 (commentable_type 이 QaPost/QaAnswer) ───
        $commentTypes = [QaPost::class, QaAnswer::class];
        $comments = DB::table('comments')
            ->whereIn('commentable_type', $commentTypes)
            ->select('id', 'commentable_type', 'commentable_id')
            ->get();
        $this->info('💭 댓글 ' . $comments->count() . '개');

        if ($comments->count() > 0) {
            $bar = $this->output->createProgressBar($comments->count());
            $bar->start();
            $answerDateCache = [];
            foreach ($comments as $c) {
                $base = null;
                if ($c->commentable_type === QaPost::class) {
                    $base = $postDates[$c->commentable_id] ?? null;
                } else {
                    if (!isset($answerDateCache[$c->commentable_id])) {
                        $row = DB::table('qa_answers')->where('id', $c->commentable_id)->first(['created_at']);
                        $answerDateCache[$c->commentable_id] = $row ? Carbon::parse($row->created_at) : null;
                    }
                    $base = $answerDateCache[$c->commentable_id];
                }
                if (!$base) { $bar->advance(); continue; }
                $afterSec = max(60, abs($now->diffInSeconds($base)));
                $d = $base->copy()->addSeconds(mt_rand(0, $afterSec));
                if (!$dry) {
                    DB::table('comments')->where('id', $c->id)->update([
                        'created_at' => $d,
                        'updated_at' => $d,
                    ]);
                }
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
        }

        $this->info('✅ 완료');
        return 0;
    }
}
