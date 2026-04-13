<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 범프 컬럼 추가
        if (!Schema::hasColumn('market_items', 'bump_count')) {
            Schema::table('market_items', function (Blueprint $table) {
                $table->unsignedTinyInteger('bump_count')->default(0)->after('boosted_until');
                $table->timestamp('last_bumped_at')->nullable()->after('bump_count');
                $table->timestamp('bumped_at')->nullable()->after('last_bumped_at');
            });
        }

        // 장터 관련 point_settings 기본값 추가 (없으면)
        $settings = [
            ['key' => 'market_max_same_category_daily', 'value' => '1', 'label' => '동일 카테고리 하루 최대 등록', 'category' => 'market'],
            ['key' => 'market_max_photos', 'value' => '10', 'label' => '글당 최대 사진 수', 'category' => 'market'],
            ['key' => 'market_free_photos', 'value' => '5', 'label' => '무료 사진 수', 'category' => 'market'],
            ['key' => 'market_extra_photo_cost', 'value' => '50', 'label' => '추가 사진 1장 비용(P)', 'category' => 'market'],
            ['key' => 'market_bump_base_cost', 'value' => '100', 'label' => '끌어올리기 기본 비용(P)', 'category' => 'market'],
            ['key' => 'market_bump_increment', 'value' => '200', 'label' => '끌어올리기 회차별 증가(P)', 'category' => 'market'],
            ['key' => 'market_bump_max_times', 'value' => '5', 'label' => '끌어올리기 최대 횟수', 'category' => 'market'],
            ['key' => 'market_bump_cooldown_hours', 'value' => '24', 'label' => '끌어올리기 쿨다운(시간)', 'category' => 'market'],
        ];

        foreach ($settings as $s) {
            if (!DB::table('point_settings')->where('key', $s['key'])->exists()) {
                DB::table('point_settings')->insert(array_merge($s, ['created_at' => now(), 'updated_at' => now()]));
            }
        }
    }

    public function down(): void
    {
        Schema::table('market_items', function (Blueprint $table) {
            $table->dropColumn(['bump_count', 'last_bumped_at', 'bumped_at']);
        });
    }
};
