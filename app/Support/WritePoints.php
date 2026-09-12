<?php

namespace App\Support;

use App\Models\PointLog;
use App\Models\User;

/**
 * 게시판별 포인트 설정 전면 재설계 1단계: "글쓰기" 보상은 게시판이 몇 개든
 * 사이트 전체에서 동일 금액(point_write) + 동일 하루 한도(content_earn_daily_max)로
 * 통합 적용한다. 게시판마다 다른 금액/한도를 주는 기존 관리자 화면의
 * board.{slug}.point_write 값은 이 통합 정책 도입으로 더 이상 사용하지 않는다.
 */
class WritePoints
{
    public const COUNTED_TYPES = [
        \App\Models\Post::class,
        \App\Models\Comment::class,
        \App\Models\MarketItem::class,
        \App\Models\JobPost::class,
        \App\Models\RealEstateListing::class,
        \App\Models\Event::class,
        \App\Models\Club::class,
        \App\Models\QaPost::class,
        \App\Models\QaAnswer::class,
        \App\Models\RecipePost::class,
        \App\Models\Business::class,
        \App\Models\BusinessReview::class,
        \App\Models\GroupBuy::class,
        \App\Models\Short::class,
    ];

    public static function award(User $user, string $modelClass, int $modelId, string $reason): void
    {
        $amount = PointRules::get('post_write', 3);
        if ($amount <= 0) return;

        $dailyCap = PointRules::get('content_earn_daily_max', 3);
        $todayCount = PointLog::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->whereIn('related_type', static::COUNTED_TYPES)
            ->count();

        if ($todayCount < $dailyCap) {
            $user->addPoints($amount, $reason, 'earn', ['type' => $modelClass, 'id' => $modelId]);
        }
    }
}
