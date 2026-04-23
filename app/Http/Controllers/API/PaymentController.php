<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    // 포인트 패키지 목록 (point_settings에서 로드, 현재 유효한 할인 이벤트 반영)
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

    // Stripe PaymentIntent 생성
    public function createIntent(Request $request)
    {
        $request->validate(['package_key' => 'required|string']);

        // point_settings에서 패키지 정보 로드
        $setting = \DB::table('point_settings')->where('key', $request->package_key)->first();
        if (!$setting) return response()->json(['success' => false, 'message' => '잘못된 패키지'], 400);

        $parts = explode('|', $setting->value); // 가격|포인트|보너스
        $original = (float) ($parts[0] ?? 0);
        $points = (int) ($parts[1] ?? 0);
        $bonus = (int) ($parts[2] ?? 0);
        $totalPoints = $points + $bonus;

        // 현재 유효한 할인 이벤트 반영
        $discount = \App\Models\PricingPromotion::currentDiscount('package');
        $price = $discount > 0 ? round($original * (100 - $discount) / 100, 2) : $original;

        $pkg = ['points' => $totalPoints, 'price' => (int) round($price * 100)]; // cents

        $stripeSecret = config('services.stripe.secret');
        if (!$stripeSecret) {
            return response()->json(['success' => false, 'message' => 'Stripe 설정이 필요합니다'], 500);
        }

        try {
            \Stripe\Stripe::setApiKey($stripeSecret);

            $intent = \Stripe\PaymentIntent::create([
                'amount' => $pkg['price'],
                'currency' => 'usd',
                'metadata' => [
                    'user_id' => auth()->id(),
                    'points' => $pkg['points'],
                    'package_key' => $request->package_key,
                ],
            ]);

            // 결제 기록 생성 (대기 상태)
            Payment::create([
                'user_id' => auth()->id(),
                'stripe_payment_id' => $intent->id,
                'amount' => $pkg['price'] / 100,
                'points_purchased' => $pkg['points'],
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'client_secret' => $intent->client_secret,
                    'payment_intent_id' => $intent->id,
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
