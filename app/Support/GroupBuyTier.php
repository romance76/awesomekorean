<?php

namespace App\Support;

/**
 * 공동구매 등급 판정 — 총액 $500 미만은 소액(자동 승인), 그 이상은 사업자(등록증 + 관리자 승인).
 * (2026-10-16 사용자 결정: 소액은 인원 50명·기간 2주 이하, 사업자는 기간 1개월 이하)
 * 화면(GroupBuyCreate.vue)에도 같은 숫자가 있으니 바꿀 때 함께 바꾼다.
 */
class GroupBuyTier
{
    public const SMALL = 'small';
    public const BUSINESS = 'business';

    public const SMALL_TOTAL_LIMIT = 500;   // 예상 최대 총액이 이 값 미만이면 소액
    public const SMALL_MAX_PEOPLE = 50;
    public const SMALL_MAX_DAYS = 14;
    public const BUSINESS_MAX_DAYS = 31;

    /** 가격은 공동구매 최저가가 있으면 그것, 없으면 원래 가격. 인원은 최대 인원 */
    public static function estimatedTotal($groupPrice, $originalPrice, $maxPeople): float
    {
        $price = (float) ($groupPrice ?: $originalPrice ?: 0);
        return $price * (int) $maxPeople;
    }

    /** 최대 인원이 없으면(무제한) 총액을 알 수 없으므로 사업자 등급 */
    public static function tier($groupPrice, $originalPrice, $maxPeople): string
    {
        $max = (int) $maxPeople;
        if ($max < 1 || $max > self::SMALL_MAX_PEOPLE) return self::BUSINESS;
        return self::estimatedTotal($groupPrice, $originalPrice, $max) < self::SMALL_TOTAL_LIMIT ? self::SMALL : self::BUSINESS;
    }

    /** 해당 등급에서 허용되는 마감까지의 최대 일수 */
    public static function maxDays(string $tier): int
    {
        return $tier === self::SMALL ? self::SMALL_MAX_DAYS : self::BUSINESS_MAX_DAYS;
    }

    /** 소액 등급을 유지할 수 있는 최대 인원(가격이 높아 2명도 못 채우면 0) */
    public static function maxPeopleForSmall($groupPrice, $originalPrice): int
    {
        $price = (float) ($groupPrice ?: $originalPrice ?: 0);
        if ($price <= 0) return self::SMALL_MAX_PEOPLE;
        $fit = (int) floor((self::SMALL_TOTAL_LIMIT - 0.01) / $price);
        $fit = min(self::SMALL_MAX_PEOPLE, $fit);
        return $fit >= 2 ? $fit : 0;
    }
}
