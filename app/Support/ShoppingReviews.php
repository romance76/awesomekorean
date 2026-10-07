<?php

namespace App\Support;

use App\Models\AmazonProduct;
use App\Models\AmazonProductClick;
use App\Models\AmazonProductView;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * 내돈내산 리뷰 규칙 모음 — 홍보 문구 차단, 첫 리뷰 승인 여부, 이번주 HOT 계산.
 */
class ShoppingReviews
{
    public const HOT_CACHE = 'shopping_hot_scores_v1';

    /**
     * 리뷰 글에 넣으면 안 되는 홍보성 내용. 걸리면 사람이 읽을 사유를 돌려주고, 없으면 null.
     *  - 웹주소/도메인, 이메일, 메신저 아이디·연락 유도, 전화번호
     */
    public static function violation(string $text): ?string
    {
        $t = mb_strtolower($text);
        if (preg_match('#https?://|www\.|[a-z0-9\-]+\.(com|net|org|co|kr|io|shop|store|biz|info|me|us|app|link|ly|to)\b#iu', $t)) {
            return '리뷰 글에는 웹사이트·쇼핑몰 주소를 넣을 수 없어요. (내 사이트/가게 홍보는 금지예요)';
        }
        if (preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/iu', $t)) {
            return '리뷰 글에는 이메일 주소를 넣을 수 없어요.';
        }
        if (preg_match('/(카톡|카카오톡|카카오\s*id|텔레그램|telegram|whatsapp|왓츠앱|line\s*id|위챗|wechat|인스타|instagram|유튜브\s*채널|구독)/iu', $t)) {
            return '리뷰 글에는 SNS·메신저 아이디나 채널 홍보를 넣을 수 없어요.';
        }
        if (preg_match('/(\+?1[\s\-.]?)?\(?\d{3}\)?[\s\-.]\d{3}[\s\-.]\d{4}/u', $t)) {
            return '리뷰 글에는 전화번호를 넣을 수 없어요.';
        }
        return null;
    }

    /** 이번 리뷰가 관리자 승인을 거쳐야 하는지 — 설정이 켜져 있으면 "한 번도 공개/승인된 적이 없는 회원"의 첫 리뷰만 */
    public static function needsApproval(User $user): bool
    {
        if (PointRules::get('shopping_review_first_approval', 1) !== 1) return false;
        return !AmazonProduct::where('user_id', $user->id)->whereNotNull('published_at')->exists();
    }

    /**
     * 최근 7일 점수 = 조회 + 클릭×3 + 댓글×5. [product_id => score] (10분 캐시)
     */
    public static function weeklyScores(): array
    {
        return Cache::remember(self::HOT_CACHE, 600, function () {
            $since = now()->subDays(7);
            $scores = [];
            $add = function ($rows, int $w) use (&$scores) {
                foreach ($rows as $id => $n) $scores[$id] = ($scores[$id] ?? 0) + $n * $w;
            };
            $add(AmazonProductView::where('viewed_at', '>=', $since)->selectRaw('amazon_product_id, COUNT(*) c')->groupBy('amazon_product_id')->pluck('c', 'amazon_product_id'), 1);
            $add(AmazonProductClick::where('clicked_at', '>=', $since)->selectRaw('amazon_product_id, COUNT(*) c')->groupBy('amazon_product_id')->pluck('c', 'amazon_product_id'), 3);
            $add(Comment::where('commentable_type', AmazonProduct::class)->where('is_hidden', false)->where('created_at', '>=', $since)
                ->selectRaw('commentable_id, COUNT(*) c')->groupBy('commentable_id')->pluck('c', 'commentable_id'), 5);
            return $scores;
        });
    }

    /** 이번주 HOT 상품 id 목록 — 점수 상위 N개 중 최소 점수 이상, 공개 상태인 것만 */
    public static function hotIds(): array
    {
        $min = max(1, PointRules::get('shopping_hot_min_score', 15));
        $limit = max(0, PointRules::get('shopping_hot_count', 5));
        if ($limit === 0) return [];

        $scores = array_filter(self::weeklyScores(), fn($s) => $s >= $min);
        if (!$scores) return [];
        arsort($scores);
        $visible = AmazonProduct::whereIn('id', array_keys($scores))->where('is_active', true)->where('status', 'published')->pluck('id')->all();
        $ids = [];
        foreach ($scores as $id => $s) {
            if (in_array($id, $visible)) $ids[] = (int) $id;
            if (count($ids) >= $limit) break;
        }
        return $ids;
    }

    public static function forget(): void
    {
        Cache::forget(self::HOT_CACHE);
    }

    /** 조회 한 번 기록 — 같은 사람(로그인 id 또는 IP)이 30분 안에 다시 봐도 한 번만 */
    public static function recordView(AmazonProduct $p, ?int $userId, string $ip): void
    {
        $key = 'shopview:' . $p->id . ':' . ($userId ?: sha1($ip));
        if (!Cache::add($key, 1, now()->addMinutes(30))) return;
        $p->increment('view_count');
        AmazonProductView::create(['amazon_product_id' => $p->id, 'user_id' => $userId, 'viewed_at' => now()]);
    }
}
