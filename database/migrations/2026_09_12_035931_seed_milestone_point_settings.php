<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 게시판별 포인트 설정 2단계: 완료/참여형 보상. 실제로 상태전이 코드가
// 있는 것만 연결 — 채용확정/임대완료/이벤트완료인증/레시피 요리인증처럼
// 그 기능 자체가 코드에 없는 항목은 이번 배치에서 제외(별도 기능 개발 필요).
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $settings = [
            ['key' => 'market_sale_complete',            'label' => '장터 판매완료 보상',        'value' => '20'],
            ['key' => 'market_sale_complete_daily_max',  'label' => '장터 판매완료 하루 한도',    'value' => '10'],
            ['key' => 'club_member_join',                'label' => '동호회 신규가입 보상(개설자)', 'value' => '5'],
            ['key' => 'club_member_join_daily_max',      'label' => '동호회 신규가입 하루 한도',   'value' => '50'],
            ['key' => 'event_join',                      'label' => '이벤트 참가 보상',          'value' => '10'],
            ['key' => 'event_join_daily_max',            'label' => '이벤트 참가 하루 한도',      'value' => '10'],
            ['key' => 'groupbuy_join_bonus',              'label' => '공동구매 참여 보너스',      'value' => '10'],
            ['key' => 'groupbuy_join_bonus_daily_max',    'label' => '공동구매 참여 보너스 하루 한도', 'value' => '5'],
            ['key' => 'groupbuy_complete',                'label' => '공동구매 완료 보상(주최자)', 'value' => '100'],
            ['key' => 'business_claim_approved',          'label' => '업소 클레임 승인 보상',      'value' => '100'],
        ];
        foreach ($settings as $s) {
            DB::table('point_settings')->updateOrInsert(
                ['key' => $s['key']],
                ['category' => 'earn', 'label' => $s['label'], 'value' => $s['value'], 'description' => '', 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('point_settings')->whereIn('key', [
            'market_sale_complete', 'market_sale_complete_daily_max',
            'club_member_join', 'club_member_join_daily_max',
            'event_join', 'event_join_daily_max',
            'groupbuy_join_bonus', 'groupbuy_join_bonus_daily_max',
            'groupbuy_complete', 'business_claim_approved',
        ])->delete();
    }
};
