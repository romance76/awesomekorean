<?php

namespace App\Support;

use App\Models\BannerAd;
use App\Models\Payment;
use App\Models\User;

/**
 * 광고(배너·텍스트 광고) 카드 결제 — Kay 결정(2026-10-11): 광고는 포인트로 살 수 없고 카드(USD)로만.
 * NEW 전면광고와 같은 흐름: 신청 때 카드 "보류"만 → 관리자 승인 때 청구 → 거절·취소 때 청구 없이 보류 해제.
 *
 * 가격 단위: 예전 포인트 가격을 그대로 센트로 본다(예전 포인트 판매 비율 100P = $1 → 1P = 1¢).
 *   예) 왼쪽 프리미엄 최저 8,000 → $80.00. 입찰 순위 계산(bid_amount)은 예전과 같은 숫자로 그대로 비교된다.
 * Stripe 최소 결제 금액 $0.50 아래로는 청구하지 않는다(오픈 이벤트 할인 후에도).
 * 주의: Stripe 카드 보류는 약 7일 뒤 자동으로 풀린다 → 그 안에 승인해야 청구된다(풀렸으면 승인 시 안내).
 */
class AdPayments
{
    public const MIN_CENTS = 50;

    public static function isCard(BannerAd $b): bool
    {
        return ($b->payment_method ?? 'points') === 'card';
    }

    public static function cents(int $amount): int
    {
        return max(self::MIN_CENTS, $amount);
    }

    public static function money(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }

    public static function payment(BannerAd $b): ?Payment
    {
        return $b->payment_id ? Payment::find($b->payment_id) : null;
    }

    /** 카드 보류 시작 — 반환: client_secret (화면이 카드를 확인할 때 씀) */
    public static function begin(User $user, BannerAd $b, string $kind, int $cents, string $description): string
    {
        [$payment, $secret] = app(DirectPayments::class)->begin($user, $kind, BannerAd::class, $b->id, $cents, $description);
        $b->update(['payment_id' => $payment->id, 'payment_method' => 'card']);
        return $secret;
    }

    /** 카드 확인(보류) 완료 검증 — Stripe 에 직접 물어본다 */
    public static function confirm(BannerAd $b): void
    {
        $p = self::payment($b);
        if (!$p) throw new \DomainException('결제를 진행 중인 신청이 아니에요. 처음부터 다시 신청해 주세요.');
        app(DirectPayments::class)->markAuthorized($p);
    }

    /** 관리자 승인 → 청구. 실패하면 DomainException(쉬운 문구) */
    public static function capture(BannerAd $b): int
    {
        $p = self::payment($b);
        if (!$p) throw new \DomainException('이 광고의 카드 결제 기록을 찾지 못했어요.');
        if ($p->status === 'captured') return (int) round($p->amount * 100);
        try {
            return app(DirectPayments::class)->capture($p);
        } catch (\DomainException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::warning("광고 카드 청구 실패(배너 #{$b->id}): " . $e->getMessage());
            throw new \DomainException('카드 청구에 실패했어요. 카드 보류가 만료(약 7일)됐거나 카드사에서 거절했을 수 있어요. 광고주에게 다시 신청을 안내해 주세요.');
        }
    }

    /** 거절·취소 → 청구 없이 보류 해제 (이미 청구된 건은 건드리지 않음) */
    public static function release(BannerAd $b): void
    {
        $p = self::payment($b);
        if ($p) app(DirectPayments::class)->release($p);
    }

    public static function captured(BannerAd $b): bool
    {
        return self::payment($b)?->status === 'captured';
    }
}
