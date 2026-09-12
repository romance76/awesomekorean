<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 게시판별 포인트 설정 전면 재설계 1단계: 글쓰기 보상은 게시판이 몇 개든
// 사이트 전체에서 "하루 N건까지, 항상 동일 금액"으로 통합 적용하기로 결정.
// content_earn_daily_max는 지금까지 DB에 없어 PointRules::get() 기본값(3)만
// 쓰이고 있었고 관리자가 값을 바꿀 방법이 없었음 — 실제 행을 심어
// AdminPointSettings.vue(카테고리별 동적 렌더링)에서 바로 편집 가능하게 함.
// like_reward_* 는 "좋아요를 누르면 글쓴이에게 1P, 단 누른 사람 기준 하루 5개
// 까지만 지급" 신규 기능의 설정값.
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $settings = [
            ['category' => 'earn', 'key' => 'content_earn_daily_max', 'label' => '글쓰기 포인트 하루 한도', 'value' => '3', 'description' => '게시판 종류 무관, 글+댓글 합산 하루 최대 건수'],
            ['category' => 'earn', 'key' => 'like_reward_amount', 'label' => '좋아요 보상', 'value' => '1', 'description' => '좋아요 1개당 글쓴이 지급 포인트'],
            ['category' => 'earn', 'key' => 'like_reward_daily_max', 'label' => '좋아요 보상 하루 한도 (누른 사람 기준)', 'value' => '5', 'description' => '한 사람이 하루에 준 좋아요 중 이 건수까지만 포인트로 이어짐(초과분은 카운트만 됨)'],
        ];
        foreach ($settings as $s) {
            DB::table('point_settings')->updateOrInsert(
                ['key' => $s['key']],
                array_merge($s, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }

    public function down(): void
    {
        DB::table('point_settings')->whereIn('key', [
            'content_earn_daily_max', 'like_reward_amount', 'like_reward_daily_max',
        ])->delete();
    }
};
