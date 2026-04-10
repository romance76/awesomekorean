<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 기존 지역 채팅방을 공개(public) 타입으로 전환
        DB::table('chat_rooms')
            ->where(function ($q) {
                $q->where('name', 'like', '%한인 채팅방%')
                  ->orWhere('name', 'like', '%수다방%')
                  ->orWhere('name', 'like', '%자유%');
            })
            ->update(['type' => 'public']);
    }

    public function down(): void
    {
        DB::table('chat_rooms')->where('type', 'public')->update(['type' => 'group']);
    }
};
