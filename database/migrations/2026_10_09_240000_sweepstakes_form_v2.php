<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 경품 추첨 폼 개편 — add-only. 기존 데이터는 삭제하지 않음
    public function up(): void
    {
        if (Schema::hasTable('events') && !Schema::hasColumn('events', 'is_online')) {
            Schema::table('events', function (Blueprint $table) {
                $table->boolean('is_online')->default(false);
            });
        }

        if (Schema::hasTable('sweepstakes')) {
            if (!Schema::hasColumn('sweepstakes', 'prize_mode')) {
                Schema::table('sweepstakes', function (Blueprint $table) {
                    $table->string('prize_mode', 10)->default('same'); // same | tiered
                });
                // 이미 등수별 상품이 설정된 기존 추첨은 tiered 로 표시 (기존 동작 유지)
                if (Schema::hasColumn('sweepstakes', 'prize_tiers') && Schema::hasColumn('sweepstakes', 'winner_count')) {
                    DB::table('sweepstakes')
                        ->whereNotNull('prize_tiers')
                        ->where('winner_count', '>', 1)
                        ->update(['prize_mode' => 'tiered']);
                }
            }

            // 2D 휠 폐지 — 추첨 화면은 3D 추첨기 하나 (기능 설정값 변경일 뿐 데이터 삭제 아님)
            if (Schema::hasColumn('sweepstakes', 'draw_style')) {
                if (DB::getDriverName() === 'mysql') {
                    DB::statement("ALTER TABLE sweepstakes MODIFY draw_style VARCHAR(20) NOT NULL DEFAULT 'lottery3d'");
                }
                DB::table('sweepstakes')->where('draw_style', '!=', 'lottery3d')->update(['draw_style' => 'lottery3d']);
            }
        }

        // 공식 규칙 기본 문서 — 키가 없을 때만 삽입 (terms_page 와 같은 site_settings 방식)
        if (Schema::hasTable('site_settings')) {
            $defaults = [
                'sweepstakes_rules_title' => \App\Http\Controllers\API\SweepstakesRulesController::defaultTitle(),
                'sweepstakes_rules_content' => \App\Http\Controllers\API\SweepstakesRulesController::defaultContent(),
                'sweepstakes_rules_version' => '1',
            ];
            foreach ($defaults as $key => $value) {
                if (!DB::table('site_settings')->where('key', $key)->exists()) {
                    DB::table('site_settings')->insert([
                        'key' => $key,
                        'value' => $value,
                        'group' => 'sweepstakes',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // add-only 마이그레이션 — 되돌리기에서 데이터를 삭제하지 않는다
    }
};
