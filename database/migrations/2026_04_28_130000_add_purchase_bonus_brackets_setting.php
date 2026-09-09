<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task 5 — 포인트 구매 커스텀 금액 방식 도입.
 * 최소 $10, $5 단위 금액 → USD 구간별 보너스 % 테이블을 point_settings 에 등록.
 * 기존 pkg_starter 등 5종 고정 패키지 행은 삭제하지 않고 그대로 둔다 (더 이상 코드에서 참조하지 않음).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('point_settings')) return;

        $brackets = json_encode([
            ['min' => 10,  'max' => 14,     'bonus_pct' => 0],
            ['min' => 15,  'max' => 19,     'bonus_pct' => 3],
            ['min' => 20,  'max' => 24,     'bonus_pct' => 5],
            ['min' => 25,  'max' => 49,     'bonus_pct' => 8],
            ['min' => 50,  'max' => 99,     'bonus_pct' => 15],
            ['min' => 100, 'max' => 199,    'bonus_pct' => 30],
            ['min' => 200, 'max' => 999999, 'bonus_pct' => 40],
        ]);

        DB::table('point_settings')->updateOrInsert(
            ['key' => 'purchase_bonus_brackets'],
            [
                'category' => 'package',
                'label' => '포인트 구매 보너스 구간 테이블',
                'value' => $brackets,
                'description' => 'JSON [{"min":$,"max":$,"bonus_pct":%}, ...] 최소 $10, $5 단위 구매',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('point_settings')) {
            DB::table('point_settings')->where('key', 'purchase_bonus_brackets')->delete();
        }
    }
};
