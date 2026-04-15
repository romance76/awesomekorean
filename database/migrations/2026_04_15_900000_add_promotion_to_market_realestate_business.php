<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 중고장터 / 부동산 / 업소록 에 상위노출(프로모션) 컬럼 추가.
 * 구인구직(job_posts) 과 동일한 스키마.
 *
 * 또한 관리자 설정(point_settings.promotion)에 리소스별 슬롯/가격 키 추가:
 *   promo_max_{resource}_{tier} = 5
 *   promo_price_{resource}_{tier} = N
 * resource ∈ {jobs, market, realestate, business}
 * tier ∈ {national, state_plus, sponsored}
 */
return new class extends Migration {
    public function up(): void
    {
        foreach (['market_items', 'real_estate_listings', 'businesses'] as $table) {
            if (!Schema::hasTable($table)) continue;
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'promotion_tier')) {
                    $t->enum('promotion_tier', ['none', 'sponsored', 'state_plus', 'national'])->default('none');
                }
                if (!Schema::hasColumn($table, 'promotion_expires_at')) {
                    $t->timestamp('promotion_expires_at')->nullable();
                }
                if (!Schema::hasColumn($table, 'promotion_states')) {
                    $t->json('promotion_states')->nullable();
                }
            });
        }

        // 리소스별 슬롯/가격 기본값
        if (Schema::hasTable('point_settings')) {
            $defaults = [
                'jobs'       => ['national' => [5, 100], 'state_plus' => [5, 50], 'sponsored' => [PHP_INT_MAX, 20]],
                'market'     => ['national' => [5, 100], 'state_plus' => [5, 50], 'sponsored' => [PHP_INT_MAX, 20]],
                'realestate' => ['national' => [5, 100], 'state_plus' => [5, 50], 'sponsored' => [PHP_INT_MAX, 20]],
                'business'   => ['national' => [5, 100], 'state_plus' => [5, 50], 'sponsored' => [PHP_INT_MAX, 20]],
            ];
            $rows = [];
            foreach ($defaults as $resource => $tiers) {
                foreach ($tiers as $tier => [$max, $price]) {
                    if ($tier !== 'sponsored') {
                        $rows[] = [
                            'category' => 'promotion',
                            'key' => "promo_max_{$resource}_{$tier}",
                            'label' => strtoupper($resource) . " / " . ($tier === 'national' ? '전국' : '주(State)') . " 카테고리당 슬롯",
                            'value' => (string) $max,
                            'description' => "{$resource} {$tier} 최대 슬롯 (카테고리 × 주 그룹별)",
                        ];
                    }
                    $rows[] = [
                        'category' => 'promotion',
                        'key' => "promo_price_{$resource}_{$tier}",
                        'label' => strtoupper($resource) . " / " . ($tier === 'national' ? '전국' : ($tier === 'state_plus' ? '주(State)' : '스폰서')) . " 하루 가격(P)",
                        'value' => (string) $price,
                        'description' => "{$resource} {$tier} 1일 포인트",
                    ];
                }
            }
            foreach ($rows as $r) {
                DB::table('point_settings')->updateOrInsert(
                    ['key' => $r['key']],
                    array_merge($r, ['updated_at' => now(), 'created_at' => now()])
                );
            }
        }
    }

    public function down(): void
    {
        foreach (['market_items', 'real_estate_listings', 'businesses'] as $table) {
            if (!Schema::hasTable($table)) continue;
            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['promotion_tier', 'promotion_expires_at', 'promotion_states'] as $col) {
                    if (Schema::hasColumn($table, $col)) $t->dropColumn($col);
                }
            });
        }
    }
};
