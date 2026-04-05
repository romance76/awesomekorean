<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PointLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * POST /api/payments/create-intent
     * Create Stripe PaymentIntent for point purchase.
     */
    public function createIntent(Request $request)
    {
        $request->validate([
            'amount' => 'required|integer|min:500',
            'points' => 'required|integer|min:100',
        ]);

        $stripeSecret = config('services.stripe.secret');
        if (!$stripeSecret) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe가 설정되지 않았습니다.',
            ], 500);
        }

        $response = Http::asForm()
            ->withBasicAuth($stripeSecret, '')
            ->post('https://api.stripe.com/v1/payment_intents', [
                'amount'               => $request->amount,
                'currency'             => 'usd',
                'metadata[user_id]'    => auth()->id(),
                'metadata[points]'     => $request->points,
            ]);

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'message' => '결제 인텐트 생성에 실패했습니다.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'client_secret' => $response->json('client_secret'),
                'amount'        => $request->amount,
                'points'        => $request->points,
            ],
        ]);
    }

    /**
     * POST /api/payments/confirm
     * Confirm payment and add points.
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        $stripeSecret = config('services.stripe.secret');
        $paymentIntentId = $request->payment_intent_id;

        // Fetch PaymentIntent from Stripe
        $response = Http::asForm()
            ->withBasicAuth($stripeSecret, '')
            ->get("https://api.stripe.com/v1/payment_intents/{$paymentIntentId}");

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'message' => '결제 정보를 조회할 수 없습니다.',
            ], 500);
        }

        $pi = $response->json();

        if (($pi['status'] ?? '') !== 'succeeded') {
            return response()->json([
                'success' => false,
                'message' => '결제가 완료되지 않았습니다.',
            ], 400);
        }

        $userId = $pi['metadata']['user_id'] ?? auth()->id();
        $points = (int) ($pi['metadata']['points'] ?? 0);

        // Prevent duplicate processing
        $exists = Payment::where('stripe_payment_id', $paymentIntentId)->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => '이미 처리된 결제입니다.',
            ], 400);
        }

        return DB::transaction(function () use ($userId, $points, $pi, $paymentIntentId) {
            $user = \App\Models\User::findOrFail($userId);
            $user->increment('points', $points);

            Payment::create([
                'user_id'           => $userId,
                'transaction_id'    => Str::uuid(),
                'type'              => 'point_purchase',
                'amount'            => ($pi['amount'] ?? 0) / 100,
                'currency'          => 'usd',
                'payment_method'    => 'stripe',
                'status'            => 'completed',
                'stripe_payment_id' => $paymentIntentId,
                'paid_at'           => now(),
            ]);

            PointLog::create([
                'user_id'       => $userId,
                'type'          => 'purchase',
                'action'        => 'earn',
                'amount'        => $points,
                'balance_after' => $user->fresh()->points,
                'memo'          => "포인트 구매: {$points}P",
            ]);

            return response()->json([
                'success' => true,
                'message' => "포인트 {$points}P가 충전되었습니다.",
                'data'    => [
                    'points'  => $points,
                    'balance' => $user->fresh()->points,
                ],
            ]);
        });
    }
}
