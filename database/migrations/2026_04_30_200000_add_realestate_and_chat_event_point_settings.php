<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 이벤트 #125(부동산 리스팅 2배 포인트), #127(오픈 채팅방 참여 포인트)를
 * 실제로 자동 지급하기 위한 설정값 시딩. 지급 자체는 각 이벤트의
 * start_date~end_date 기간에만 이뤄지도록 컨트롤러에서 events 테이블을
 * 직접 조회해 확인 — 기간이 지나면 관리자가 별도로 끌 필요 없이 자동 종료됨.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('point_settings')) {
            DB::table('point_settings')->updateOrInsert(
                ['key' => 'realestate_listing_base'],
                [
                    'category' => 'earn',
                    'label' => '부동산 리스팅 등록 포인트 (기본)',
                    'value' => '20',
                    'description' => '리스팅 1건 등록 시 지급. 이벤트#125 기간 중에는 2배 지급됨',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            DB::table('point_settings')->updateOrInsert(
                ['key' => 'realestate_listing_daily_max'],
                [
                    'category' => 'earn',
                    'label' => '부동산 리스팅 포인트 일일 상한 (건수)',
                    'value' => '2',
                    'description' => '하루 이 건수까지만 포인트 지급 (등록 자체는 제한 없음)',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        if (Schema::hasTable('chat_settings')) {
            DB::table('chat_settings')->updateOrInsert(
                ['key' => 'chat_first_join_bonus'],
                [
                    'category' => 'earn',
                    'label' => '오픈 채팅방 첫 참여 포인트',
                    'value' => '20',
                    'description' => '공개 채팅방에 처음 메시지를 보낼 때 1회 지급 (이벤트#127 기간에만)',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            DB::table('chat_settings')->updateOrInsert(
                ['key' => 'chat_daily_bonus'],
                [
                    'category' => 'earn',
                    'label' => '오픈 채팅방 매일 참여 포인트',
                    'value' => '5',
                    'description' => '공개 채팅방에 그날 처음 메시지를 보낼 때 지급 (이벤트#127 기간에만)',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('point_settings')) {
            DB::table('point_settings')->whereIn('key', ['realestate_listing_base', 'realestate_listing_daily_max'])->delete();
        }
        if (Schema::hasTable('chat_settings')) {
            DB::table('chat_settings')->whereIn('key', ['chat_first_join_bonus', 'chat_daily_bonus'])->delete();
        }
    }
};
