<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 광고 프로모션 이벤트의 시작·종료 날짜를 정오 무렵 시각으로 — 자정(UTC)으로 저장하면 미국 동부 시간으로 바뀌며 하루 앞(10월 20일~1월 30일)으로 보였다.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('events')->where('title', '📣 광고 신청 한정 프로모션 — 광고비 최대 90% 할인')->update([
            'start_date' => '2026-10-21 16:00:00',   // 동부 낮 12시
            'end_date' => '2027-01-31 17:00:00',     // 동부 낮 12시
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
    }
};
