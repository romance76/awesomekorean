<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    // Task 5 — 커스텀 금액 구매: 최소 $10, $5 단위. DB `purchase_bonus_brackets` 미설정 시 폴백.
    private const DEFAULT_BONUS_BRACKETS = [
        ['min' => 10,  'max' => 14,     'bonus_pct' => 0],
        ['min' => 15,  'max' => 19,     'bonus_pct' => 3],
        ['min' => 20,  'max' => 24,     'bonus_pct' => 5],
        ['min' => 25,  'max' => 49,     'bonus_pct' => 8],
        ['min' => 50,  'max' => 99,     'bonus_pct' => 15],
        ['min' => 100, 'max' => 199,    'bonus_pct' => 30],
        ['min' => 200, 'max' => 999999, 'bonus_pct' => 40],
    ];

    private function bonusBracketTable(): array
    {
        $raw = \App\Support\PointRules::raw('purchase_bonus_brackets', json_encode(self::DEFAULT_BONUS_BRACKETS));
        $brackets = json_decode($raw, true);
        return is_array($brackets) && !empty($brackets) ? $brackets : self::DEFAULT_BONUS_BRACKETS;
    }

    private function bonusPctForAmount(float $amount): int
    {
        foreach ($this->bonusBracketTable() as $b) {
            if ($amount >= ($b['min'] ?? 0) && $amount <= ($b['max'] ?? 0)) {
                return (int) ($b['bonus_pct'] ?? 0);
            }
        }
        return 0;
    }

    /** 금액 → 지급 포인트 (amount * 100 * (1 + bonus%/100), 반올림) */
    private function pointsForAmount(float $amount): int
    {
        $bonusPct = $this->bonusPctForAmount($amount);
        return (int) round($amount * 100 * (1 + $bonusPct / 100));
    }

    /** 커스텀 금액 구매용 보너스 구간 테이블 (프론트 실시간 미리보기용) */
    public function bonusBrackets()
    {
        $discount = \App\Models\PricingPromotion::currentDiscount('package'); // 0~95, 결제 금액에만 적용
        return response()->json([
            'success' => true,
            'data' => [
                'brackets' => $this->bonusBracketTable(),
                'min_amount' => 10,
                'increment' => 5,
                'discount_pct' => $discount,
            ],
        ]);
    }

    // 포인트 패키지 목록 (레거시, 더 이상 프론트에서 사용하지 않음 — point_settings pkg_* 행이 남아있는 동안만 동작)
    public function packages()
    {
        $discount = \App\Models\PricingPromotion::currentDiscount('package'); // 0~95
        $pkgs = \DB::table('point_settings')
            ->where('key', 'like', 'pkg_%')
            ->orderBy('id')
            ->get()
            ->map(function ($s) use ($discount) {
                $parts = explode('|', $s->value);
                $original = (float) ($parts[0] ?? 0);
                $final = $discount > 0 ? round($original * (100 - $discount) / 100, 2) : $original;
                return [
                    'key' => $s->key,
                    'name' => $s->label ?? $s->key,
                    'price' => $final,                  // 실제 결제 금액
                    'original_price' => $original,      // 원가 (UI 취소선용)
                    'discount_pct' => $discount,        // 0 이면 할인 없음
                    'points' => (int) ($parts[1] ?? 0),
                    'bonus' => (int) ($parts[2] ?? 0),
                ];
            });
        return response()->json(['success' => true, 'data' => $pkgs]);
    }

    // Stripe PaymentIntent 생성 — Task 5: 고정 패키지 대신 커스텀 금액 ($10 이상, $5 단위)
    public function createIntent(Request $request)
    {
        $request->validate(['amount' => 'required|numeric|min:10']);

        $amount = round((float) $request->amount, 2);
        $amountCents = (int) round($amount * 100);
        // $10 이상 & $5 단위 검증 (센트 단위로 비교해 부동소수 오차 방지)
        if ($amountCents < 1000 || $amountCents % 500 !== 0) {
            return response()->json(['success' => false, 'message' => '금액은 $10 이상, $5 단위로 입력해주세요 (예: $10, $15, $20...)'], 422);
        }

        // 지급 포인트는 "정가" 금액 기준으로 계산 (할인이 지급 포인트를 부풀리지 않도록)
        $totalPoints = $this->pointsForAmount($amount);
        $bonusPct = $this->bonusPctForAmount($amount);

        // 현재 유효한 할인 이벤트는 실제 결제(청구) 금액에만 적용
        $discount = \App\Models\PricingPromotion::currentDiscount('package');
        $chargeAmount = $discount > 0 ? round($amount * (100 - $discount) / 100, 2) : $amount;
        $chargeCents = (int) round($chargeAmount * 100);

        $stripeSecret = config('services.stripe.secret');
        if (!$stripeSecret) {
            return response()->json(['success' => false, 'message' => 'Stripe 설정이 필요합니다'], 500);
        }

        try {
            \Stripe\Stripe::setApiKey($stripeSecret);

            $intent = \Stripe\PaymentIntent::create([
                'amount' => $chargeCents,
                'currency' => 'usd',
                'metadata' => [
                    'user_id' => auth()->id(),
                    'points' => $totalPoints,
                    'amount' => $amount,
                    'bonus_pct' => $bonusPct,
                ],
            ]);

            // 결제 기록 생성 (대기 상태)
            Payment::create([
                'user_id' => auth()->id(),
                'stripe_payment_id' => $intent->id,
                'amount' => $chargeCents / 100,
                'points_purchased' => $totalPoints,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'client_secret' => $intent->client_secret,
                    'payment_intent_id' => $intent->id,
                    'points_purchased' => $totalPoints,
                    'bonus_pct' => $bonusPct,
                    'charge_amount' => $chargeAmount,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => '결제 생성 실패: ' . $e->getMessage()], 500);
        }
    }

    // 결제 확인 + 포인트 지급
    public function confirm(Request $request)
    {
        $request->validate(['payment_intent_id' => 'required|string']);

        $payment = Payment::where('stripe_payment_id', $request->payment_intent_id)
            ->where('user_id', auth()->id())
            ->where('status', 'pending')
            ->first();

        if (!$payment) {
            return response()->json(['success' => false, 'message' => '결제 정보를 찾을 수 없습니다'], 404);
        }

        // 이전에는 로컬 Payment 행이 pending 상태로 존재하기만 하면 Stripe에
        // 실제로 결제가 완료됐는지 전혀 확인하지 않고 바로 포인트를 지급했음
        // (웹훅도 없었음) — 클라이언트가 이 엔드포인트만 호출하면 실제 결제
        // 없이 포인트를 받을 수 있던 치명적 취약점(실측 확인). Stripe에서
        // PaymentIntent를 직접 조회해 결제가 실제로 완료됐는지 검증한 뒤에만
        // 지급하도록 수정.
        $stripeSecret = config('services.stripe.secret');
        if (!$stripeSecret) {
            return response()->json(['success' => false, 'message' => 'Stripe 설정이 필요합니다'], 500);
        }
        try {
            \Stripe\Stripe::setApiKey($stripeSecret);
            $intent = \Stripe\PaymentIntent::retrieve($payment->stripe_payment_id);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Stripe 결제 확인 실패: ' . $e->getMessage()], 502);
        }
        if ($intent->status !== 'succeeded') {
            return response()->json(['success' => false, 'message' => '결제가 아직 완료되지 않았습니다 (상태: ' . $intent->status . ')'], 422);
        }
        // 결제 금액도 우리가 발급했던 금액과 일치하는지 확인(위변조 방지)
        if ((int) $intent->amount !== (int) round($payment->amount * 100)) {
            return response()->json(['success' => false, 'message' => '결제 금액이 일치하지 않습니다'], 422);
        }

        // 포인트 지급
        $user = auth()->user();
        $user->addPoints($payment->points_purchased, "포인트 구매 ({$payment->points_purchased}P)");

        $payment->update(['status' => 'completed']);

        return response()->json([
            'success' => true,
            'data' => [
                'points_added' => $payment->points_purchased,
                'total_points' => $user->fresh()->points,
            ],
            'message' => "{$payment->points_purchased}P가 지급되었습니다!",
        ]);
    }

    // 결제 내역
    public function history()
    {
        $payments = Payment::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $payments]);
    }
}
