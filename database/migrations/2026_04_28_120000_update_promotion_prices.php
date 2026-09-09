<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task 4 — 상위노출/광고 진입 가격 인하 (하루 P 가격).
 * promo_price_sponsored 20→17, promo_price_state_plus 50→40, promo_price_national 100→80.
 * 값이 없으면 아무 것도 하지 않음(원래 시드 마이그레이션이 먼저 실행되어야 함).
 * 순수 UPDATE 이므로 재실행해도 안전.
 */
return new class extends Migration {
    private const UPDATES = [
        'promo_price_sponsored' => '17',
        'promo_price_state_plus' => '40',
        'promo_price_national' => '80',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('point_settings')) return;

        foreach (self::UPDATES as $key => $value) {
            DB::table('point_settings')->where('key', $key)->update([
                'value' => $value,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('point_settings')) return;

        $originals = [
            'promo_price_sponsored' => '20',
            'promo_price_state_plus' => '50',
            'promo_price_national' => '100',
        ];
        foreach ($originals as $key => $value) {
            DB::table('point_settings')->where('key', $key)->update([
                'value' => $value,
                'updated_at' => now(),
            ]);
        }
    }
};
