<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 앱 코드가 실제로 읽고 쓰는데 어떤 마이그레이션에도 정의돼 있지 않던
 * 테이블/컬럼들. 운영 DB에는 수동으로 추가돼 있을 가능성이 높지만 확실치
 * 않으므로 전부 "없을 때만 생성"으로 처리 — 운영에선 이미 있으면 그대로
 * 두고, 없으면 이번에 만들어지며, 신규 환경에서도 앱이 정상 동작하게 됨.
 * (로컬에서 전체 API를 실제로 호출해보는 점검 중에 발견)
 */
return new class extends Migration
{
    public function up(): void
    {
        // UpdateLastActive 미들웨어가 로그인 사용자의 모든 요청마다 갱신
        if (!Schema::hasColumn('users', 'last_active_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('last_active_at')->nullable();
            });
        }

        // ClubController::createChatRoom — 동호회 전용 채팅방
        if (!Schema::hasColumn('clubs', 'chat_room_id')) {
            Schema::table('clubs', function (Blueprint $table) {
                $table->unsignedBigInteger('chat_room_id')->nullable();
            });
        }

        // MusicController::toggleFavorite / favorites
        if (!Schema::hasTable('music_favorites')) {
            Schema::create('music_favorites', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('track_id');
                $table->timestamp('created_at')->nullable();
                $table->unique(['user_id', 'track_id']);
            });
        }

        // ShortController — 본 숏츠 기록(피드에서 이미 본 영상 뒤로 보내기)
        if (!Schema::hasTable('short_views')) {
            Schema::create('short_views', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('short_id');
                $table->timestamp('viewed_at')->nullable();
                $table->unique(['user_id', 'short_id']);
            });
        }
    }

    public function down(): void
    {
        // 운영 DB에 원래 있던 것일 수 있으므로 되돌리기에서 삭제하지 않음
    }
};
