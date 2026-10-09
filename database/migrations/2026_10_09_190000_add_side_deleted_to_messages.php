<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 쪽지를 보낸 사람/받은 사람이 각자 자기 쪽지함에서만 삭제할 수 있도록 삭제 표시 컬럼 추가
// (기존 쪽지는 모두 false = 지금처럼 그대로 보임. 데이터 삭제·변경 없음)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'sender_deleted')) $table->boolean('sender_deleted')->default(false);
            if (!Schema::hasColumn('messages', 'receiver_deleted')) $table->boolean('receiver_deleted')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['sender_deleted', 'receiver_deleted']);
        });
    }
};
