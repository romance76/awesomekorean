<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 공개 채팅방 입장료(24시간 이용권) 기능:
 * - chat_room_users.access_expires_at: 이 시각까지는 그 공개방에 유효한
 *   입장권이 있는 것으로 간주(블러 없이 열람 가능). null/과거면 미입장.
 * - users.free_public_room_id: 회원이 마이페이지에서 고른, 입장료 없이
 *   항상 무료로 드나들 수 있는 공개 채팅방 1개.
 * - chat_settings에 entry_cost_public 기본값 시딩(관리자 조정 가능).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_room_users', function (Blueprint $table) {
            $table->timestamp('access_expires_at')->nullable()->after('left_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('free_public_room_id')->nullable()->after('default_radius')
                ->constrained('chat_rooms')->nullOnDelete();
        });

        if (Schema::hasTable('chat_settings')) {
            DB::table('chat_settings')->updateOrInsert(
                ['key' => 'entry_cost_public'],
                [
                    'category' => 'create_cost',
                    'label' => '공개 채팅방 입장료 (24시간 이용권)',
                    'value' => '10',
                    'description' => '공개 채팅방에 처음 입장하거나 24시간 이용권이 만료된 뒤 다시 입장할 때 차감되는 포인트. 마이페이지에서 지정한 "무료 채팅방" 1개는 제외.',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'free_public_room_id')) {
                $table->dropConstrainedForeignId('free_public_room_id');
            }
        });
        Schema::table('chat_room_users', function (Blueprint $table) {
            if (Schema::hasColumn('chat_room_users', 'access_expires_at')) {
                $table->dropColumn('access_expires_at');
            }
        });
        if (Schema::hasTable('chat_settings')) {
            DB::table('chat_settings')->where('key', 'entry_cost_public')->delete();
        }
    }
};
