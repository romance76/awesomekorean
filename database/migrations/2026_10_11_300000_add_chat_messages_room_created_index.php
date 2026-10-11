<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 채팅방 입장이 느린 문제: 방의 메시지를 "방 번호로 거르고 시간순으로 정렬해 50개"만 읽는데,
 * (chat_room_id, created_at) 색인이 없어서 큰 방은 그 방의 모든 메시지를 정렬한 뒤 잘라 냈다.
 * 색인 하나만 추가한다(데이터는 건드리지 않음). 이미 있으면 건너뛰고, 실패해도 배포를 막지 않는다. down() 은 아무것도 안 함.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chat_messages')) return;
        try {
            if (Schema::hasIndex('chat_messages', ['chat_room_id', 'created_at'])) return;
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->index(['chat_room_id', 'created_at'], 'chat_messages_room_created_idx');
            });
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function down(): void
    {
        // 데이터 보호 규칙에 따라 되돌리지 않는다(색인은 남겨 둬도 무해).
    }
};
