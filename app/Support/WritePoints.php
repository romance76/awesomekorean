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
        \App\Models\AmazonProduct::class,
    ];

    /**
     * 응답을 먼저 보낸 뒤(defer) 포인트를 지급한다 — 글/답변 저장 직후 "등록" 반응이 느려지지 않게.
     * 포인트 집계·Entry 보상·뱃지 판정 쿼리가 여러 번 나가므로 사용자가 기다릴 필요가 없다.
     * 실패해도 글 작성에는 영향이 없고 로그만 남긴다.
     */
    public static function awardLater(?User $user, string $modelClass, int $modelId, string $reason): void
    {
        if (!$user) return;
        defer(function () use ($user, $modelClass, $modelId, $reason) {
            try {
                static::award($user, $modelClass, $modelId, $reason);
            } catch (\Throwable $e) {
                \Log::warning("[포인트] {$reason} 지급 실패 (user_id={$user->id}): " . $e->getMessage());
            }
        });
    }

    public static function award(User $user, string $modelClass, int $modelId, string $reason): void
    {
        $amount = PointRules::get('post_write', 3);
        $dailyCap = PointRules::get('content_earn_daily_max', 3);
        $todayCount = PointLog::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->whereIn('related_type', static::COUNTED_TYPES)
            ->count();

        if ($amount > 0 && $todayCount < $dailyCap) {
            $user->addPoints($amount, $reason, 'earn', ['type' => $modelClass, 'id' => $modelId]);

            // Entry 활동 보상 — 포인트가 실제로 지급된 작성만 집계하므로 하루 포인트 한도가
            // 그대로 도배 방지 역할을 한다. Entry 쪽 오류가 글 작성을 막으면 안 됨.
            try { EntryService::recordActivity($user); }
            catch (\Throwable $e) { \Log::warning("Entry 활동 보상 실패 (user_id={$user->id}): " . $e->getMessage()); }
        }

        // 활동 뱃지는 하루 지급 한도와 무관하게 실제 작성 건수 기준으로 판정
        \App\Services\BadgeService::checkWriteBadges($user);
    }
}
