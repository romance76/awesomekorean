<?php

namespace App\Support;

use App\Models\PostLike;
use App\Models\ShortLike;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 좋아요를 누르면 "글쓴이"에게 포인트가 지급되는 신규 기능.
 * 한도는 좋아요를 준 사람(누른 사람) 기준 하루 N개까지만 지급되고,
 * 그 이후 누른 좋아요는 집계(카운트)만 되고 포인트로 이어지지 않는다.
 * PostLike / ShortLike / QA 답변 좋아요(qa_answer_likes) 전부 같은 하루
 * 한도를 공유한다 (좋아요 기능이 실제로 있는 곳 전체 = 사이트 전체 통합).
 */
class LikeRewards
{
    public static function likesGivenToday(int $likerId): int
    {
        return PostLike::where('user_id', $likerId)->whereDate('created_at', today())->count()
            + ShortLike::where('user_id', $likerId)->whereDate('created_at', today())->count()
            + DB::table('qa_answer_likes')->where('user_id', $likerId)->where('type', 'like')->whereDate('created_at', today())->count();
    }

    public static function award(int $likerId, ?User $author, ?string $relatedType = null, ?int $relatedId = null): void
    {
        if (!$author || $author->id === $likerId) return; // 본인 글에 본인이 좋아요 눌러도 지급 안 함

        $amount = PointRules::get('like_reward_amount', 1);
        if ($amount <= 0) return;

        $dailyMax = PointRules::get('like_reward_daily_max', 5);
        // 방금 생성된 좋아요까지 포함한 "오늘 누른 좋아요 개수"이므로,
        // 이 값이 한도 이하일 때(=이번이 오늘의 N번째 이하 좋아요일 때)만 지급.
        if (static::likesGivenToday($likerId) > $dailyMax) return;

        $author->addPoints($amount, '좋아요 받음', 'like_reward', $relatedType ? ['type' => $relatedType, 'id' => $relatedId] : null);
    }
}
