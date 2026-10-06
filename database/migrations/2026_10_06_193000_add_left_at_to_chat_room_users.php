<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 공개 채팅방 사이드바의 "참가중인 채팅방"(내가 글을 쓴 공개방) 섹션에서
 * "나가기"를 누르면 그 사실을 기록해두는 컬럼. null이면 계속 참가중,
 * 값이 있으면 나간 상태 — 나간 뒤 그 방에 다시 글을 쓰면 sendMessage()에서
 * 다시 null로 돌려놓아(재참가) 목록에 다시 뜨게 함.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_room_users', function (Blueprint $table) {
            $table->timestamp('left_at')->nullable()->after('last_read_at');
        });
    }

    public function down(): void
    {
        Schema::table('chat_room_users', function (Blueprint $table) {
            if (Schema::hasColumn('chat_room_users', 'left_at')) {
                $table->dropColumn('left_at');
            }
        });
    }
};
