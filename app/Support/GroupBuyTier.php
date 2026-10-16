<?php

namespace App\Support;

/**
 * 공동구매 등급 판정 — 총액 $500 미만은 소액(자동 승인), 그 이상은 사업자(등록증 + 관리자 승인).
 * (2026-10-16 사용자 결정: 소액은 인원 50명·수량 50개·기간 2주 이하, 사업자는 기간 1개월 이하)
 * 화면(GroupBuyCreate.vue)에도 같은 숫자가 있으니 바꿀 때 함께 바꾼다.
 */
class GroupBuyTier
{
    public const SMALL = 'small';
    public const BUSINESS = 'business';

    public const SMALL_TOTAL_LIMIT = 500;   // 예상 최대 총액이 이 값 미만이면 소액
    public const SMALL_MAX_PEOPLE = 50;
    public const SMALL_MAX_QUANTITY = 50;
    public const SMALL_MAX_DAYS = 14;
    public const BUSINESS_MAX_DAYS = 31;

    /** 팔 수 있는 개수: 총 수량이 있으면 그것, 없으면 최대 인원(1인 1개로 보고) */
    public static function items($maxPeople, $quantity): int
    {
        $q = (int) $quantity;
        return $q > 0 ? $q : (int) $maxPeople;
    }

    /** 가격은 공동구매 최저가가 있으면 그것, 없으면 원래 가격 */
    public static function estimatedTotal($groupPrice, $originalPrice, $maxPeople, $quantity = null): float
    {
        $price = (float) ($groupPrice ?: $originalPrice ?: 0);
        return $price * self::items($maxPeople, $quantity);
    }

    /** 범위: local = 작성자 주 + 인접 주(기본), national = 전국 */
    public const SCOPE_LOCAL = 'local';
    public const SCOPE_NATIONAL = 'national';
    /** 전국 공동구매 디파짓 배수 (2026-10-16 우선 책정) */
    public const NATIONAL_DEPOSIT_MULTIPLIER = 3;

    /**
     * 최대 인원이 없으면(무제한) 총액을 알 수 없으므로 사업자 등급. 인원·수량이 소액 한도를 넘어도 사업자.
     * 전국 공동구매는 총액과 상관없이 항상 사업자 등급(등록증 + 관리자 승인).
     */
    public static function tier($groupPrice, $originalPrice, $maxPeople, $quantity = null, string $scope = self::SCOPE_LOCAL): string
    {
        if ($scope === self::SCOPE_NATIONAL) return self::BUSINESS;
        $max = (int) $maxPeople;
        $qty = (int) $quantity;
        if ($max < 1 || $max > self::SMALL_MAX_PEOPLE || $qty > self::SMALL_MAX_QUANTITY) return self::BUSINESS;
        return self::estimatedTotal($groupPrice, $originalPrice, $max, $qty) < self::SMALL_TOTAL_LIMIT ? self::SMALL : self::BUSINESS;
    }

    /**
     * 열 때 받는 디파짓(달러, 환불 안 함). 소액은 $5, 사업자는 기간별 $5(3일)/$7(1주)/$10(2주)/$15(1개월), 전국은 거기에 ×3.
     * 결제 기능이 열리기 전까지는 안내용 숫자다.
     */
    public static function deposit(string $tier, string $scope, int $days): int
    {
        if ($tier === self::SMALL) {
            $base = 5;
        } else {
            $base = $days <= 3 ? 5 : ($days <= 7 ? 7 : ($days <= 14 ? 10 : 15));
        }
        return $base * ($scope === self::SCOPE_NATIONAL ? self::NATIONAL_DEPOSIT_MULTIPLIER : 1);
    }

    /**
     * 완료 때 총액 수수료(센트) — 총액(센트)에 구간별 요율. 구간제(이상~미만), 범위(지역/전국)와 상관없이 같은 표.
     *   $500 미만 정액 $5 / $500~999 2% / $1,000~1,999 2% / $2,000~2,999 3.5% / $3,000~3,999 5% / $4,000~4,999 6% / $5,000~9,999 7% / $10,000 이상 8%
     * 총액이 0이면 수수료도 0. 이 값에서 이미 낸 디파짓을 빼고 모자란 만큼만 결제한다.
     */
    public static function feeCents(int $totalCents): int
    {
        if ($totalCents <= 0) return 0;
        if ($totalCents < 50000) return 500;
        // [이 금액(센트) 이상이면, 요율의 만분율(bp)]
        $bands = [[1000000, 800], [500000, 700], [400000, 600], [300000, 500], [200000, 350], [50000, 200]];
        foreach ($bands as [$from, $bp]) {
            if ($totalCents >= $from) return (int) round($totalCents * $bp / 10000);
        }
        return 500;
    }

    /** 해당 등급에서 허용되는 마감까지의 최대 일수 */
    public static function maxDays(string $tier): int
    {
        return $tier === self::SMALL ? self::SMALL_MAX_DAYS : self::BUSINESS_MAX_DAYS;
    }

    /** 소액 등급을 유지할 수 있는 최대 개수(가격이 높아 2개도 못 채우면 0). 인원·수량 둘 다 이 값 이하로 맞추면 된다 */
    public static function maxPeopleForSmall($groupPrice, $originalPrice): int
    {
        $price = (float) ($groupPrice ?: $originalPrice ?: 0);
        if ($price <= 0) return min(self::SMALL_MAX_PEOPLE, self::SMALL_MAX_QUANTITY);
        $fit = (int) floor((self::SMALL_TOTAL_LIMIT - 0.01) / $price);
        $fit = min(self::SMALL_MAX_PEOPLE, self::SMALL_MAX_QUANTITY, $fit);
        return $fit >= 2 ? $fit : 0;
    }
}
