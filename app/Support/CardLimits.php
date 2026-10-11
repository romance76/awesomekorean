<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * 카드 결제 최소 금액 — 관리자 › 사이트 설정 › 결제/알림 설정 › "최소 결제금액"(site_settings.payment_config.min_amount, 기본 $5)이 기준이다.
 * 카드 결제는 이 금액 이상일 때만 받는다(Stripe 자체 최소는 $0.50 이라 그 아래로는 설정해도 $0.50 으로 본다).
 * 포인트 결제는 해당 없음. 오픈 이벤트 할인 등으로 청구액이 이보다 작아지면 "최소 결제 금액"으로 올려 청구한다(광고).
 */
class CardLimits
{
    public const STRIPE_MIN_CENTS = 50;

    public static function minCents(): int
    {
        $usd = 5.0;
        try {
            $raw = SiteSetting::where('key', 'payment_config')->value('value');
            $cfg = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($cfg) && isset($cfg['min_amount']) && is_numeric($cfg['min_amount'])) $usd = (float) $cfg['min_amount'];
        } catch (\Throwable $e) {
            // 설정을 못 읽으면 기본값 $5
        }
        return max(self::STRIPE_MIN_CENTS, (int) round(min($usd, 1000) * 100));
    }

    public static function money(?int $cents = null): string
    {
        return '$' . number_format(($cents ?? self::minCents()) / 100, 2);
    }

    /** 이 금액이 최소 미만이면 쉬운 안내 문구를 던진다 */
    public static function assert(int $cents): void
    {
        if ($cents < self::minCents()) {
            throw new \DomainException('카드 결제는 최소 ' . self::money() . ' 이상부터 가능해요 (지금 ' . self::money($cents) . '). 금액을 늘리거나 포인트로 결제해 주세요.');
        }
    }
}
