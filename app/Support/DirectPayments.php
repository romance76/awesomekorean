<?php

namespace App\Support;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 달러 직접 결제의 수명주기 — payments 행(kind=flyer/event_request)과 Stripe 보류를 함께 관리한다.
 *
 *   begin()           카드 보류용 intent 생성 + payments(pending)
 *   markAuthorized()  사용자가 카드를 확인(보류 성공)했는지 Stripe 에서 직접 조회해 검증 → authorized
 *   capture()         승인 — 실제 청구 (일부만 청구 가능) → captured
 *   release()         거절/취소 — 청구 없이 보류 해제 → released
 *   refund()          청구 후 일부/전액 환불 → refunded_amount 누적 (전액이면 refunded)
 *
 * 금액은 Stripe 와 맞추기 쉽게 호출부에서는 센트(int), payments.amount 는 달러(decimal).
 */
class DirectPayments
{
    public function __construct(private StripeGateway $stripe) {}

    /** @return array{0:Payment,1:string} [payment, client_secret] */
    public function begin(User $user, string $kind, ?string $refType, ?int $refId, int $cents, string $description): array
    {
        // 카드 결제 최소 금액(관리자 결제 설정의 "최소 결제금액", 기본 $5) 미만은 받지 않는다
        CardLimits::assert($cents);
        $hold = $this->stripe->createHold($cents, $description, [
            'user_id' => $user->id, 'kind' => $kind, 'ref_type' => (string) $refType, 'ref_id' => (string) $refId,
        ]);
        $payment = Payment::create([
            'user_id' => $user->id, 'kind' => $kind, 'ref_type' => $refType, 'ref_id' => $refId,
            'stripe_payment_id' => $hold['id'], 'amount' => $cents / 100, 'description' => $description, 'status' => 'pending',
        ]);
        return [$payment, $hold['client_secret']];
    }

    /** 카드 확인이 끝나 보류가 잡혔는지 확인 (클라이언트 말만 믿지 않고 Stripe 에 직접 조회) */
    public function markAuthorized(Payment $payment): void
    {
        $i = $this->stripe->retrieve($payment->stripe_payment_id);
        if ($i['status'] !== 'requires_capture') {
            throw new \DomainException('카드 결제 확인이 아직 끝나지 않았어요 (상태: ' . $i['status'] . ')');
        }
        if ($i['amount'] !== $this->cents($payment)) {
            throw new \DomainException('결제 금액이 일치하지 않아요.');
        }
        $payment->update(['status' => 'authorized']);
    }

    /** 실제 청구. $cents 가 null 이면 전액. 청구된 금액으로 payments.amount 를 맞춘다. */
    public function capture(Payment $payment, ?int $cents = null): int
    {
        if ($payment->status !== 'authorized') {
            throw new \DomainException('승인 대기(카드 보류) 상태가 아니에요.');
        }
        $r = $this->stripe->captureDetailed($payment->stripe_payment_id, $cents);
        $received = $r['received'];
        // 카드 종류·끝 4자리도 같은 저장에 넣어, 이 저장으로 자동 발행되는 인보이스에 바로 찍히게 한다
        // (카드 칸이 아직 없는 DB 에서도 청구 기록이 실패하지 않게 칸이 있을 때만 넣는다 — 이미 청구된 뒤라 저장 실패는 절대 안 됨)
        static $hasCardCols = null;
        $hasCardCols ??= \Illuminate\Support\Facades\Schema::hasColumn('payments', 'card_last4');
        $payment->update(array_filter([
            'status' => 'captured', 'amount' => $received / 100, 'captured_at' => now(),
            'card_brand' => ($hasCardCols && $r['brand']) ? mb_substr((string) $r['brand'], 0, 20) : null,
            'card_last4' => ($hasCardCols && $r['last4']) ? mb_substr((string) $r['last4'], 0, 4) : null,
        ], fn ($v) => $v !== null));
        return $received;
    }

    /** 청구 없이 보류 해제. 이미 풀렸거나 만료된 경우도 조용히 released 로 정리. */
    public function release(Payment $payment): void
    {
        if (!in_array($payment->status, ['pending', 'authorized'], true)) return;
        try {
            $this->stripe->cancel($payment->stripe_payment_id);
        } catch (\Throwable $e) {
            \Log::warning('Stripe 보류 해제 실패(이미 만료/취소됐을 수 있음): ' . $e->getMessage());
        }
        $payment->update(['status' => 'released']);
    }

    /** 청구된 금액 중 $cents 환불 */
    public function refund(Payment $payment, int $cents): void
    {
        if ($cents <= 0) return;
        DB::transaction(function () use ($payment, $cents) {
            $p = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if (!in_array($p->status, ['captured', 'refunded'], true)) throw new \DomainException('청구된 결제만 환불할 수 있어요.');
            $remaining = $this->cents($p) - (int) round($p->refunded_amount * 100);
            $cents = min($cents, $remaining);
            if ($cents <= 0) return;
            $this->stripe->refund($p->stripe_payment_id, $cents);
            $refunded = (int) round($p->refunded_amount * 100) + $cents;
            $p->update(['refunded_amount' => $refunded / 100, 'status' => $refunded >= $this->cents($p) ? 'refunded' : 'captured']);
        });
    }

    private function cents(Payment $p): int
    {
        return (int) round($p->amount * 100);
    }
}
