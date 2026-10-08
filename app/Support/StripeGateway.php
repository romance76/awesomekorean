<?php

namespace App\Support;

use Stripe\StripeClient;

/**
 * Stripe 호출을 한 곳에 모은 얇은 래퍼 — 달러 직접 결제(NEW 전면광고, 경품 이벤트 의뢰)용.
 * "카드 보류(authorize) → 승인 시 청구(capture) / 반려 시 보류 해제(cancel)" 흐름을 쓴다.
 * 테스트에서는 이 클래스를 상속해 컨테이너에 바인딩해서 가짜로 바꿀 수 있다.
 */
class StripeGateway
{
    public function configured(): bool
    {
        return (bool) config('services.stripe.secret') && (bool) config('services.stripe.key');
    }

    private function client(): StripeClient
    {
        $secret = config('services.stripe.secret');
        if (!$secret) throw new \RuntimeException('Stripe 설정이 필요합니다');
        return new StripeClient($secret);
    }

    /** 카드 보류용 PaymentIntent 생성 (capture_method=manual). @return array{id:string,client_secret:string} */
    public function createHold(int $cents, string $description, array $metadata = []): array
    {
        $intent = $this->client()->paymentIntents->create([
            'amount' => $cents,
            'currency' => 'usd',
            'capture_method' => 'manual',
            'payment_method_types' => ['card'],
            'description' => $description,
            'metadata' => $metadata,
        ]);
        return ['id' => $intent->id, 'client_secret' => $intent->client_secret];
    }

    /** @return array{id:string,status:string,amount:int,amount_received:int} */
    public function retrieve(string $id): array
    {
        $i = $this->client()->paymentIntents->retrieve($id);
        return ['id' => $i->id, 'status' => $i->status, 'amount' => (int) $i->amount, 'amount_received' => (int) $i->amount_received];
    }

    /** 보류한 금액 중 $cents(없으면 전액)만 청구. 나머지는 Stripe 가 자동으로 풀어줌. @return int 실제 청구액(센트) */
    public function capture(string $id, ?int $cents = null): int
    {
        $params = $cents !== null ? ['amount_to_capture' => $cents] : [];
        $i = $this->client()->paymentIntents->capture($id, $params);
        return (int) $i->amount_received;
    }

    /** 보류 해제 (청구 전 취소) */
    public function cancel(string $id): void
    {
        $this->client()->paymentIntents->cancel($id);
    }

    /**
     * 포인트 구매(일반 PaymentIntent) 카드 전액 환불. 이미 Stripe 대시보드에서 (일부)환불한 건은 남은 금액만 환불하고,
     * 남은 금액이 없으면 아무것도 하지 않는다. idempotency key 로 재시도해도 이중 환불이 되지 않는다.
     * @return array{result:string,cents:int} result = 'refunded'(이번에 환불) | 'already_refunded'(이미 전액 환불돼 있음)
     * @throws \DomainException 결제가 Stripe 에서 완료된 상태가 아닐 때
     */
    public function refundFull(string $paymentIntentId, string $idempotencyKey): array
    {
        $pi = $this->client()->paymentIntents->retrieve($paymentIntentId, ['expand' => ['latest_charge']]);
        $charge = $pi->latest_charge ?? null;
        if ($pi->status !== 'succeeded' || !$charge || is_string($charge)) {
            throw new \DomainException('Stripe 에서 결제가 완료된 상태가 아니라 카드 환불을 할 수 없어요 (상태: ' . $pi->status . ')');
        }
        $refundable = (int) $charge->amount - (int) $charge->amount_refunded;
        if ($refundable <= 0) return ['result' => 'already_refunded', 'cents' => 0];
        $this->client()->refunds->create(
            ['payment_intent' => $paymentIntentId, 'amount' => $refundable],
            ['idempotency_key' => $idempotencyKey]
        );
        return ['result' => 'refunded', 'cents' => $refundable];
    }

    /** 청구된 결제의 일부/전부 환불 */
    public function refund(string $id, int $cents): void
    {
        $this->client()->refunds->create(['payment_intent' => $id, 'amount' => $cents]);
    }
}
