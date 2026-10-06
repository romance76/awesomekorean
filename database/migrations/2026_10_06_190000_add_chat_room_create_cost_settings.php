<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 무분별한 채팅방 생성을 막기 위해 방 개설 시 포인트를 차감하는 기능
 * (ChatController::createRoom)을 관리자 페이지에서 조정할 수 있도록
 * chat_settings 에 기본값을 시딩. ChatRules::get()이 이 값을 읽어가며,
 * 행이 없으면 코드의 기본값(50/200/500)을 그대로 쓰므로 이 마이그레이션은
 * 관리자 UI 노출/조정용일 뿐 기능 자체의 전제조건은 아님.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('chat_settings')) return;

        DB::table('chat_settings')->updateOrInsert(
            ['key' => 'create_cost_dm'],
            [
                'category' => 'create_cost',
                'label' => '1:1 채팅방 개설 비용',
                'value' => '50',
                'description' => '1:1 채팅방을 새로 만들 때 차감되는 포인트 (기존 방 재사용 시엔 차감 안 됨)',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
        DB::table('chat_settings')->updateOrInsert(
            ['key' => 'create_cost_group'],
            [
                'category' => 'create_cost',
                'label' => '그룹 채팅방 개설 비용',
                'value' => '200',
                'description' => '그룹 채팅방을 만들 때 차감되는 포인트',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
        DB::table('chat_settings')->updateOrInsert(
            ['key' => 'create_cost_public'],
            [
                'category' => 'create_cost',
                'label' => '공개 채팅방 개설 비용',
                'value' => '500',
                'description' => '공개 채팅방을 만들 때 차감되는 포인트',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('chat_settings')) {
            DB::table('chat_settings')->whereIn('key', ['create_cost_dm', 'create_cost_group', 'create_cost_public'])->delete();
        }
    }
};
