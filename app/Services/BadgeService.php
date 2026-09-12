<?php

namespace App\Services;

use App\Events\NewNotification;
use App\Models\Business;
use App\Models\BusinessReview;
use App\Models\Club;
use App\Models\Comment;
use App\Models\Event;
use App\Models\GroupBuy;
use App\Models\JobPost;
use App\Models\MarketItem;
use App\Models\Notification;
use App\Models\Post;
use App\Models\QaAnswer;
use App\Models\QaPost;
use App\Models\RealEstateListing;
use App\Models\RecipePost;
use App\Models\Short;
use App\Models\User;
use App\Models\UserBadge;

/**
 * 회원 뱃지(업적) 시스템. 10종을 4개 카테고리(활동/인기/Q&A/신뢰)로 정의하고,
 * 이미 있는 포인트 지급 훅(WritePoints/LikeRewards)과 기존 승인 플로우에
 * 편승해 판정한다 — 매번 전수 스캔하는 배치잡 없이 이벤트 발생 시점에만 체크.
 */
class BadgeService
{
    public const DEFS = [
        'first_post'        => ['label' => '첫 발자국',      'icon' => '👣', 'desc' => '글/댓글 첫 작성'],
        'active_writer'     => ['label' => '열심히 활동중',   'icon' => '✍️', 'desc' => '글/댓글 50개 작성'],
        'content_master'    => ['label' => '콘텐츠 마스터',   'icon' => '📚', 'desc' => '글/댓글 300개 작성'],
        'popular_50'        => ['label' => '인기글 작성자',   'icon' => '❤️', 'desc' => '좋아요 50개 받기'],
        'influencer_300'    => ['label' => '인플루언서',      'icon' => '🔥', 'desc' => '좋아요 300개 받기'],
        'qa_answerer_5'     => ['label' => '답변왕',          'icon' => '💡', 'desc' => 'Q&A 채택 답변 5회'],
        'qa_expert_30'      => ['label' => '지식인',          'icon' => '🧠', 'desc' => 'Q&A 채택 답변 30회'],
        'verified_business' => ['label' => '인증 업소주',     'icon' => '🏪', 'desc' => '업소 소유주 인증 완료'],
        'anniversary_1y'    => ['label' => '1주년 회원',      'icon' => '🎂', 'desc' => '가입 1년 달성'],
        'anniversary_3y'    => ['label' => '창립 멤버',       'icon' => '🌟', 'desc' => '가입 3년 달성'],
    ];

    public static function award(User $user, string $key): void
    {
        if (!isset(self::DEFS[$key])) return;
        if (UserBadge::where('user_id', $user->id)->where('badge_key', $key)->exists()) return;

        UserBadge::create(['user_id' => $user->id, 'badge_key' => $key, 'earned_at' => now()]);

        $def = self::DEFS[$key];
        try {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'badge_earned',
                'title' => "새 뱃지 획득: {$def['icon']} {$def['label']}",
                'content' => $def['desc'],
                'data' => ['badge_key' => $key],
            ]);
            $unread = Notification::where('user_id', $user->id)->whereNull('read_at')->count();
            broadcast(new NewNotification($user->id, $unread, "새 뱃지 획득: {$def['label']}"))->toOthers();
        } catch (\Exception $e) {}
    }

    public static function checkWriteBadges(User $user): void
    {
        $total = static::countWrites($user->id);
        if ($total >= 1) static::award($user, 'first_post');
        if ($total >= 50) static::award($user, 'active_writer');
        if ($total >= 300) static::award($user, 'content_master');
    }

    public static function checkLikeBadges(User $user): void
    {
        $total = static::countLikesReceived($user->id);
        if ($total >= 50) static::award($user, 'popular_50');
        if ($total >= 300) static::award($user, 'influencer_300');
    }

    public static function checkQaBadges(User $user): void
    {
        $accepted = QaAnswer::where('user_id', $user->id)->where('is_best', true)->count();
        if ($accepted >= 5) static::award($user, 'qa_answerer_5');
        if ($accepted >= 30) static::award($user, 'qa_expert_30');
    }

    public static function checkAnniversary(User $user): void
    {
        if (!$user->created_at) return;
        $years = $user->created_at->diffInYears(now());
        if ($years >= 1) static::award($user, 'anniversary_1y');
        if ($years >= 3) static::award($user, 'anniversary_3y');
    }

    public static function countWrites(int $userId): int
    {
        return Post::where('user_id', $userId)->count()
            + Comment::where('user_id', $userId)->count()
            + MarketItem::where('user_id', $userId)->count()
            + JobPost::where('user_id', $userId)->count()
            + RealEstateListing::where('user_id', $userId)->count()
            + Event::where('user_id', $userId)->count()
            + Club::where('user_id', $userId)->count()
            + QaPost::where('user_id', $userId)->count()
            + QaAnswer::where('user_id', $userId)->count()
            + RecipePost::where('user_id', $userId)->count()
            + Business::where('user_id', $userId)->count()
            + BusinessReview::where('user_id', $userId)->count()
            + GroupBuy::where('user_id', $userId)->count()
            + Short::where('user_id', $userId)->count();
    }

    public static function countLikesReceived(int $userId): int
    {
        return (int) Post::where('user_id', $userId)->sum('like_count')
            + (int) Short::where('user_id', $userId)->sum('like_count')
            + (int) QaAnswer::where('user_id', $userId)->sum('like_count');
    }
}
