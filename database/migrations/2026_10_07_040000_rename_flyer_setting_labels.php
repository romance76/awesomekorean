<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 관리자 가격/할인 센터에서 한눈에 찾도록 전단 광고 설정 라벨을 "NEW 전면광고"로 통일.
 * (값은 건드리지 않음 — 라벨/설명만 변경)
 */
return new class extends Migration
{
    public function up(): void
    {
        $labels = [
            'flyer_price_national' => 'NEW 전면광고 · 시간당 가격 (전국, P)',
            'flyer_price_state'    => 'NEW 전면광고 · 시간당 가격 (내 지역=주, P)',
            'flyer_peak_pct'       => 'NEW 전면광고 · 피크 시간대(17~22시) 가격 배율 (%)',
            'flyer_night_pct'      => 'NEW 전면광고 · 심야 시간대(0~5시) 가격 배율 (%)',
            'flyer_max_days'       => 'NEW 전면광고 · 1회 신청 최대 일수',
            'flyer_window_days'    => 'NEW 전면광고 · 시작일 예약 가능 기간(일)',
        ];
        foreach ($labels as $key => $label) {
            DB::table('point_settings')->where('key', $key)->update(['label' => $label, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // no-op
    }
};
