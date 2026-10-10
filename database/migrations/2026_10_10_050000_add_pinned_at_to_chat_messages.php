<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 방장이 자기 글을 채팅방 상단에 고정(공지)하는 기능 — 방당 최대 3개.
// 기존 pinned_until 은 관리자 시스템 공지(시간 제한)용이라 따로 pinned_at 을 둔다.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_messages', 'pinned_at')) {
                $table->timestamp('pinned_at')->nullable()->after('pinned_until');
                $table->index(['chat_room_id', 'pinned_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (Schema::hasColumn('chat_messages', 'pinned_at')) {
                $table->dropIndex(['chat_room_id', 'pinned_at']);
                $table->dropColumn('pinned_at');
            }
        });
    }
};
