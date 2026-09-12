<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// PromotionSettings::RESOURCES에 'clubs'가 빠져있어 동호회 상위노출이
// 관리자 설정과 무관하게 항상 하드코딩 기본값(100/50/20P, 5슬롯)만 쓰던
// 문제 수정의 일부 — jobs/market/realestate/business와 동일한 기본값을
// clubs 전용 키로도 심어 관리자 화면(AdminPointSettings.vue, category=promotion)
// 에서 다른 4개 리소스와 동일하게 편집 가능하도록 함.
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $tiers = [
            'national'   => ['slots' => 5, 'price' => 100],
            'state_plus' => ['slots' => 5, 'price' => 50],
            'sponsored'  => ['slots' => 999999, 'price' => 20],
        ];
        foreach ($tiers as $tier => $v) {
            DB::table('point_settings')->updateOrInsert(
                ['key' => "promo_max_clubs_{$tier}"],
                ['category' => 'promotion', 'label' => "동호회 상위노출 슬롯 ({$tier})", 'value' => (string) $v['slots'], 'description' => '', 'created_at' => $now, 'updated_at' => $now]
            );
            DB::table('point_settings')->updateOrInsert(
                ['key' => "promo_price_clubs_{$tier}"],
                ['category' => 'promotion', 'label' => "동호회 상위노출 가격 ({$tier})", 'value' => (string) $v['price'], 'description' => 'P/일', 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('point_settings')->where('key', 'like', 'promo_%_clubs_%')->delete();
    }
};
