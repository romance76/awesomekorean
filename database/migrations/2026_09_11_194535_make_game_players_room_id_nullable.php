<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 솔로(1인) 게임 점수 저장 시 존재하지 않는 game_rooms.id=0 을 강제로 참조해
// FK 제약 위반으로 500 에러가 발생하던 문제 수정 (실측 확인). game_room_id를
// nullable로 바꾸고, 솔로 플레이는 NULL로 저장하도록 함께 수정.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_players', function ($table) {
            $table->dropForeign(['game_room_id']);
        });

        DB::statement('ALTER TABLE game_players MODIFY game_room_id BIGINT UNSIGNED NULL');

        // 기존에 억지로 넣혀 있던 0 값(있었다면)을 NULL로 정리
        DB::table('game_players')->where('game_room_id', 0)->update(['game_room_id' => null]);

        Schema::table('game_players', function ($table) {
            $table->foreign('game_room_id')->references('id')->on('game_rooms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function ($table) {
            $table->dropForeign(['game_room_id']);
        });

        DB::table('game_players')->whereNull('game_room_id')->update(['game_room_id' => 0]);

        DB::statement('ALTER TABLE game_players MODIFY game_room_id BIGINT UNSIGNED NOT NULL');

        Schema::table('game_players', function ($table) {
            $table->foreign('game_room_id')->references('id')->on('game_rooms')->cascadeOnDelete();
        });
    }
};
