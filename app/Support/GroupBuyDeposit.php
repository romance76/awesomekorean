<?php

namespace App\Support;

use App\Models\GroupBuy;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * 공동구매 개설 디파짓 — 기존 DirectPayments(카드 보류 → 청구/해제)를 공동구매에 맞게 쓰는 얇은 층.
 *
 *   begin()     임시 글(awaiting_deposit) + 카드 보류 결제 생성
 *   confirm()   카드 확인(보류)을 Stripe 에서 검증 → 소액은 바로 청구·공개, 사업자는 보류 유지(관리자 승인 때 청구)
 *   capture()   관리자 승인 때 청구 (보류가 만료됐으면 오류)
 *   release()   반려·취소 때 청구 없이 보류 해제
 *   purge()     결제를 마치지 않은 임시 글 정리(보류 해제 + 파일·글 삭제)
 *
 * 디파짓은 환불하지 않는다: 청구된 뒤(소액 즉시 / 사업자 승인 후)에는 되돌리지 않고, 청구 전(보류)인 것만 풀린다.
 */
class GroupBuyDeposit
{
    /** 카드를 입력하다 떠난 임시 글을 자동 정리하기까지 걸리는 시간(분) */
    public const CHECKOUT_MINUTES = 60;

    public static function payment(GroupBuy $gb): ?Payment
    {
        return $gb->deposit_payment_id ? Payment::find($gb->deposit_payment_id) : null;
    }

    /** @return array{0:Payment,1:string} [payment, client_secret] */
    public static function begin(GroupBuy $gb, User $user, int $cents): array
    {
        [$payment, $secret] = app(DirectPayments::class)->begin(
            $user, Payment::KIND_GROUPBUY, GroupBuy::class, $gb->id, $cents,
            "공동구매 개설 디파짓: {$gb->title}"
        );
        $gb->update(['deposit_cents' => $cents, 'deposit_payment_id' => $payment->id]);
        return [$payment, $secret];
    }

    /**
     * 카드 확인이 끝났는지 검증하고 글을 연다.
     * @return string 'captured'(소액: 청구 완료·바로 공개) | 'held'(사업자: 보류 유지·관리자 승인 대기)
     * @throws \DomainException 카드 확인이 아직 끝나지 않았거나 금액이 다를 때
     */
    public static function confirm(GroupBuy $gb): string
    {
        $payment = self::payment($gb);
        if (!$payment) throw new \DomainException('결제 정보를 찾을 수 없어요. 처음부터 다시 등록해 주세요.');
        $dp = app(DirectPayments::class);
        $dp->markAuthorized($payment);

        $tier = GroupBuyTier::tier($gb->group_price, $gb->original_price, $gb->max_participants, $gb->total_quantity, $gb->scope ?: 'local');
        if ($tier === GroupBuyTier::SMALL) {
            $dp->capture($payment);
            $gb->update(['status' => 'recruiting', 'is_approved' => true, 'approved_at' => now()]);
            return 'captured';
        }
        $gb->update(['status' => 'recruiting']);   // 승인 전이라 is_approved=false 그대로 — 목록에는 안 나오고 관리자 승인 대기에 나온다
        return 'held';
    }

    /** 관리자 승인 때 청구. 보류가 없거나 이미 청구됐으면 아무것도 하지 않는다. @throws \DomainException 보류가 만료돼 청구할 수 없을 때 */
    public static function capture(GroupBuy $gb): void
    {
        $payment = self::payment($gb);
        if (!$payment || $payment->status !== 'authorized') return;
        try {
            app(DirectPayments::class)->capture($payment);
        } catch (\DomainException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::warning('공동구매 디파짓 청구 실패(보류가 만료됐을 수 있음): ' . $e->getMessage());
            throw new \DomainException('개설자의 카드 보류가 만료돼 청구하지 못했어요. 반려하고 개설자에게 다시 등록해 달라고 안내해 주세요.');
        }
    }

    /** 청구 없이 보류 해제 (이미 청구된 것은 건드리지 않는다) */
    public static function release(GroupBuy $gb): void
    {
        $payment = self::payment($gb);
        if ($payment) app(DirectPayments::class)->release($payment);
    }

    /** 결제를 마치지 않은 임시 글 하나를 지운다: 보류 해제 + 이미지 삭제 + 글 삭제(서류는 모델이 같이 지움) */
    public static function purge(GroupBuy $gb): void
    {
        self::release($gb);
        foreach ((array) $gb->images as $img) {
            Storage::disk('public')->delete($img);
        }
        $gb->delete();
    }

    /** 카드 보류 기간(6일)이 지나도록 승인되지 않은 사업자 공동구매를 자동 반려(보류 해제) — Stripe 보류는 약 7일 뒤 만료되기 때문 */
    public const HOLD_DAYS = 6;

    public static function expireHolds(): int
    {
        $n = 0;
        $list = GroupBuy::where('status', 'recruiting')->where('is_approved', false)->whereNull('rejection_reason')->whereNotNull('deposit_payment_id')->get();
        foreach ($list as $gb) {
            $p = self::payment($gb);
            if (!$p || $p->status !== 'authorized' || $p->updated_at->gt(now()->subDays(self::HOLD_DAYS))) continue;
            self::release($gb);
            $gb->update(['rejection_reason' => '승인이 지연되어 카드 보류가 만료되기 전에 자동 취소됐어요. 카드에는 청구되지 않았으니 필요하면 다시 등록해 주세요.']);
            \App\Models\Notification::create([
                'user_id' => $gb->user_id, 'type' => 'groupbuy_rejected', 'title' => '공동구매 등록이 자동 취소되었어요',
                'content' => "'{$gb->title}' 공동구매가 오래 승인되지 않아 자동 취소되었어요. 카드에는 청구되지 않았습니다. 필요하면 다시 등록해 주세요.",
                'data' => ['groupbuy_id' => $gb->id],
            ]);
            $n++;
        }
        return $n;
    }

    /** 오래된 임시 글 정리. $userId 가 있으면 그 회원의 임시 글을 (시간과 상관없이) 모두 정리 — 새로 등록할 때 이전 시도를 치운다 */
    public static function purgeStale(?int $userId = null): int
    {
        $q = GroupBuy::where('status', 'awaiting_deposit');
        if ($userId) $q->where('user_id', $userId);
        else $q->where('created_at', '<', now()->subMinutes(self::CHECKOUT_MINUTES));
        $n = 0;
        foreach ($q->get() as $gb) {
            self::purge($gb);
            $n++;
        }
        return $n;
    }
}
