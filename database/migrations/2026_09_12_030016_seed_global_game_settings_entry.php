<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// "전역 게임당 포인트"(point_per_game, game_type='global') 기본값은
// GameScoreController가 이미 읽고 있지만, 이를 수정할 관리자 화면 진입
// 경로가 없었음(감사 확인) — AdminGameSettings.vue는 games 테이블의 실제
// 게임 slug로만 접근 가능해, slug='global'인 가상의 "게임" 행을 하나
// 심어서 기존 화면(신규 프론트 코드 없이)으로 전역 기본값을 직접 편집할
// 수 있게 함. is_active=false라 유저 게임 목록에는 노출되지 않음.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('games')->updateOrInsert(
            ['slug' => 'global'],
            [
                'name' => '⚙️ 전역 설정 (모든 게임 공통 기본값)',
                'description' => '개별 게임에 설정이 없을 때 쓰이는 공통 기본값 (예: point_per_game)',
                'icon' => '⚙️',
                'category' => 'brain',
                'path' => '/games',
                'is_active' => false,
                'sort_order' => 9999,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('games')->where('slug', 'global')->delete();
    }
};
