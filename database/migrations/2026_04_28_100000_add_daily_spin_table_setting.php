<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task 1 — 일일 룰렛 가중치 테이블을 point_settings 에 등록.
 * 기존 daily_spin_rewards(균등분포) 를 대체하는 새 키 daily_spin_table (JSON, 가중치 합=100).
 * updateOrInsert 이므로 이미 값이 존재하는 환경에서 재실행해도 안전.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('point_settings')) return;

        $table = json_encode([
            ['value' => 0, 'weight' => 60],
            ['value' => 1, 'weight' => 20],
            ['value' => 2, 'weight' => 10],
            ['value' => 5, 'weight' => 6],
            ['value' => 10, 'weight' => 3],
            ['value' => 30, 'weight' => 1],
        ]);

        DB::table('point_settings')->updateOrInsert(
            ['key' => 'daily_spin_table'],
            [
                'category' => 'earn',
                'label' => '일일 룰렛 가중치 테이블',
                'value' => $table,
                'description' => 'JSON [{"value":P,"weight":%}, ...] 가중치 합계 100 기준 (기대값 1.3P/일)',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('point_settings')) {
            DB::table('point_settings')->where('key', 'daily_spin_table')->delete();
        }
    }
};
