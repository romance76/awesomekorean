<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 채팅방 잠금 관련 컬럼 추가
        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->timestamp('last_message_at')->nullable()->after('created_by');
            $table->timestamp('locked_at')->nullable()->after('last_message_at');
        });

        // 기존 방들의 last_message_at 을 실제 메시지 기록 기준으로 백필
        DB::table('chat_rooms')->orderBy('id')->chunkById(200, function ($rooms) {
            foreach ($rooms as $r) {
                $last = DB::table('chat_messages')->where('chat_room_id', $r->id)->max('created_at');
                DB::table('chat_rooms')->where('id', $r->id)->update(['last_message_at' => $last ?: $r->created_at]);
            }
        });

        // 채팅 설정 테이블 (관리자 수정 가능) - point_settings 와 동일한 형태
        if (!Schema::hasTable('chat_settings')) {
            Schema::create('chat_settings', function (Blueprint $table) {
                $table->id();
                $table->string('category', 50);
                $table->string('key', 100)->unique();
                $table->string('label');
                $table->string('value');
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        // 기본 설정값 시드
        DB::table('chat_settings')->updateOrInsert(
            ['key' => 'inactive_lock_days'],
            [
                'category' => 'lock',
                'label' => '비활성 잠금 기준일',
                'value' => '7',
                'description' => '개인/그룹 채팅방에 이 기간(일) 동안 새 메시지가 없으면 자동으로 잠깁니다 (공개 채팅방 제외)',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
        DB::table('chat_settings')->updateOrInsert(
            ['key' => 'lock_delete_days'],
            [
                'category' => 'lock',
                'label' => '잠금 후 삭제까지 기간',
                'value' => '3',
                'description' => '잠긴 채팅방은 이 기간(일)이 지나면 메시지를 포함해 완전히 삭제됩니다',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            if (Schema::hasColumn('chat_rooms', 'locked_at')) {
                $table->dropColumn('locked_at');
            }
            if (Schema::hasColumn('chat_rooms', 'last_message_at')) {
                $table->dropColumn('last_message_at');
            }
        });

        Schema::dropIfExists('chat_settings');
    }
};
