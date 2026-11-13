<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 미리 만들어 둔 Q&A 가 너무 많아 절반을 줄인다 (2026-11-13 사용자 요청).
// 대상: 가상 회원(@test.com · @awesomekorean.local)이 쓴 질문 중 번호가 짝수인 것 — 번호 순서대로 한 칸 건너 지워 분야·날짜가 고르게 줄어든다.
// 실제 회원이 쓴 질문, 실제 회원이 답변을 단 질문, 현상금이 걸린 질문은 건드리지 않는다.
// 질문을 지우면 답변·답변 좋아요는 외래키 cascade 로 함께 지워진다. 여러 번 돌려도 이미 지워진 건 다시 지우지 않는다.
// (삭제 전 상태는 매일 DB 백업에 남아 있음)
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('qa_posts') || !Schema::hasTable('qa_answers')) return;

        $isDummy = fn ($q) => $q->where('email', 'like', '%@test.com')->orWhere('email', 'like', '%@awesomekorean.local');
        $dummyIds = DB::table('users')->where($isDummy)->pluck('id')->all();
        if (!$dummyIds) return;

        $before = DB::table('qa_posts')->count();
        $deleted = 0;
        DB::table('qa_posts')
            ->whereIn('user_id', $dummyIds)
            ->whereRaw('id % 2 = 0')
            ->where('bounty_points', 0)
            ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('qa_answers')
                ->whereColumn('qa_answers.qa_post_id', 'qa_posts.id')->whereNotIn('qa_answers.user_id', $dummyIds))
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$deleted) {
                $ids = $rows->pluck('id')->all();
                DB::table('qa_posts')->whereIn('id', $ids)->delete();
                $deleted += count($ids);
            });

        \Log::info("[Q&A 절반 줄이기] {$before}개 중 {$deleted}개 삭제");
    }

    public function down(): void
    {
        // 되돌리지 않는다 (DB 백업에서 복원).
    }
};
