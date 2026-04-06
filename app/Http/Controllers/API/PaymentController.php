<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    // 포인트 패키지 목록
    public function packages()
    {
        return response()->json(['success' => true, 'data' => [
            ['id' => 1, 'name' => '스타터', 'points' => 100, 'price' => 0.99, 'bonus' => 0],
            ['id' => 2, 'name' => '베이직', 'points' => 500, 'price' => 4.99, 'bonus' => 50],
            ['id' => 3, 'name' => '프리미엄', 'points' => 1000, 'price' => 9.99, 'bonus' => 200],
            ['id' => 4, 'name' => '프로', 'points' => 5000, 'price' => 39.99, 'bonus' => 1500],
            ['id' => 5, 'name' => '울트라', 'points' => 10000, 'price' => 69.99, 'bonus' => 4000],
        ]]);
    }

    // Stripe PaymentIntent 생성
    public function createIntent(Request $request)
    {
        $request->validate(['package_id' => 'required|integer']);

        $packages = [
            1 => ['points' => 100, 'price' => 99], // cents
            2 => ['points' => 550, 'price' => 499],
            3 => ['points' => 1200, 'price' => 999],
            4 => ['points' => 6500, 'price' => 3999],
            5 => ['points' => 14000, 'price' => 6999],
        ];

        $pkg = $packages[$request->package_id] ?? null;
        if (!$pkg) return response()->json(['success' => false, 'message' => '잘못된 패키지'], 400);

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
                    'package_id' => $request->package_id,
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
