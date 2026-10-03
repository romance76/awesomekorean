<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Entry 시스템 도입에 맞춘 Point 밸런스 조정. 회원가입 보너스(signup_bonus),
// 프로필 완성 보너스(profile_complete_bonus), 좋아요 보상(like_reward_amount),
// 글쓰기류 공유 일일 한도(content_earn_daily_max)는 이미 목표값과 동일하거나
// 변경 대상이 아니라서 건드리지 않음. 기존 point_settings row만 값을 바꾸고
// 구조/카테고리는 그대로 유지 — 사용자 보유 포인트(users.points)는 전혀 건드리지 않음.
return new class extends Migration
{
    private array $changes = [
        'post_write' => 2,                       // 3 → 2
        'comment_write' => 1,                    // 3 → 1
        'qa_answer_accepted' => 10,               // 20 → 10
        'market_sale_complete' => 10,             // 20 → 10
        'club_member_join' => 1,                  // 5 → 1
        'event_join' => 0,                        // 10 → 0
        'groupbuy_join_bonus' => 5,                // 10 → 5
        'groupbuy_complete' => 50,                 // 100 → 50
        'business_claim_approved' => 50,           // 100 → 50
        'job_hire_complete' => 20,                 // 30 → 20
        'realestate_rent_complete' => 50,          // 100 → 50
    ];

    public function up(): void
    {
        foreach ($this->changes as $key => $value) {
            DB::table('point_settings')->where('key', $key)->update([
                'value' => (string) $value,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $previous = [
            'post_write' => 3,
            'comment_write' => 3,
            'qa_answer_accepted' => 20,
            'market_sale_complete' => 20,
            'club_member_join' => 5,
            'event_join' => 10,
            'groupbuy_join_bonus' => 10,
            'groupbuy_complete' => 100,
            'business_claim_approved' => 100,
            'job_hire_complete' => 30,
            'realestate_rent_complete' => 100,
        ];
        foreach ($previous as $key => $value) {
            DB::table('point_settings')->where('key', $key)->update([
                'value' => (string) $value,
                'updated_at' => now(),
            ]);
        }
    }
};
