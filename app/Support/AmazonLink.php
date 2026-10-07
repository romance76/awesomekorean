<?php

namespace App\Support;

/**
 * Amazon Associates 링크 생성/파싱 헬퍼. Associate 태그는
 * config('services.amazon_associates.associate_tag') 한 곳에서만 읽어서, 태그가 바뀌어도
 * 여기 한 곳만 고치면 됨(코드 곳곳에 하드코딩하지 않음). ('amazon' 키는 소셜 로그인용
 * Socialite 설정이 따로 쓰고 있어서 충돌을 피하려고 'amazon_associates'로 분리함.)
 */
class AmazonLink
{
    // ASIN 그대로 입력했거나, amazon.com 상품 URL(/dp/ASIN, /gp/product/ASIN)에서 ASIN을 뽑아냄
    public static function extractAsin(string $input): ?string
    {
        $input = trim($input);

        if (preg_match('/^[A-Z0-9]{10}$/', $input)) {
            return $input;
        }

        if (preg_match('#/(?:dp|gp/product|gp/aw/d)/([A-Z0-9]{10})#i', $input, $m)) {
            return strtoupper($m[1]);
        }

        if (preg_match('/[?&]asin=([A-Z0-9]{10})/i', $input, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    public static function amazonUrl(string $asin): string
    {
        return "https://www.amazon.com/dp/{$asin}";
    }

    /** $tag 가 없으면 사이트 기본 태그, 있으면(회원 리뷰) 그 회원의 태그로 링크를 만든다 */
    public static function affiliateUrl(string $asin, ?string $tag = null): string
    {
        $tag = $tag ?: config('services.amazon_associates.associate_tag');
        return "https://www.amazon.com/dp/{$asin}?tag=" . rawurlencode($tag);
    }

    /** Amazon Associates 스토어 ID 형식 — 영문/숫자/하이픈, 끝이 -숫자2자리 (예: abc-20, myshop-21) */
    public static function isValidTag(?string $tag): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9\-]{1,30}-\d{2}$/i', trim((string) $tag));
    }
}
