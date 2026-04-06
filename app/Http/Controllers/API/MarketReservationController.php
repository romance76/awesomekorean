<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MarketItem;
use App\Models\MarketReservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketReservationController extends Controller
{
    // 찜하기 (포인트 에스크로)
    public function reserve(Request $request, $id)
    {
        $item = MarketItem::findOrFail($id);

        if ($item->status !== 'active') {
            return response()->json(['success' => false, 'message' => '이미 예약된 물품입니다'], 400);
        }

        if ($item->user_id === auth()->id()) {
            return response()->json(['success' => false, 'message' => '본인 물품은 예약할 수 없습니다'], 400);
        }

        $escrowPoints = $request->points ?? 100;
        $user = auth()->user();

        if ($user->points < $escrowPoints) {
            return response()->json(['success' => false, 'message' => '포인트가 부족합니다 (필요: ' . $escrowPoints . 'P, 보유: ' . $user->points . 'P)'], 400);
        }

        return DB::transaction(function () use ($item, $user, $escrowPoints) {
            // 포인트 차감
            $user->decrement('points', $escrowPoints);
            $user->pointLogs()->create([
                'amount' => -$escrowPoints, 'type' => 'spend',
                'reason' => '장터 예약 에스크로', 'balance_after' => $user->fresh()->points,
                'related_type' => 'App\\Models\\MarketItem', 'related_id' => $item->id,
            ]);

            // 예약 생성
            $reservation = MarketReservation::create([
                'market_item_id' => $item->id,
                'buyer_id' => $user->id,
                'seller_id' => $item->user_id,
                'points_held' => $escrowPoints,
                'status' => 'pending',
            ]);

            // 물품 상태 변경
            $item->update(['status' => 'reserved']);

            return response()->json(['success' => true, 'data' => $reservation, 'message' => '예약되었습니다. ' . $escrowPoints . 'P가 에스크로 됩니다.']);
        });
    }

    // 거래 완료 (에스크로 해제 → 판매자에게)
    public function complete($id)
    {
        $reservation = MarketReservation::where('seller_id', auth()->id())
            ->where('status', 'pending')
            ->findOrFail($id);

        return DB::transaction(function () use ($reservation) {
            // 판매자에게 포인트 지급
            $seller = $reservation->seller;
            $seller->addPoints($reservation->points_held, '장터 거래 완료 (에스크로 수령)');

            // 상태 업데이트
            $reservation->update(['status' => 'completed', 'completed_at' => now()]);
            $reservation->item->update(['status' => 'sold']);

            return response()->json(['success' => true, 'message' => '거래가 완료되었습니다. ' . $reservation->points_held . 'P가 지급됩니다.']);
        });
    }

    // 거래 취소 (50:50 분할)
    public function cancel($id)
    {
        $reservation = MarketReservation::where('status', 'pending')
            ->where(function ($q) {
                $q->where('buyer_id', auth()->id())->orWhere('seller_id', auth()->id());
            })
            ->findOrFail($id);

        return DB::transaction(function () use ($reservation) {
            $half = intval($reservation->points_held / 2);
            $buyerReturn = $reservation->points_held - $half;

            // 구매자에게 50% 반환
            $reservation->buyer->addPoints($buyerReturn, '장터 예약 취소 (50% 반환)');

            // 판매자에게 50% 지급 (노쇼 방지)
            if ($half > 0) {
                $reservation->seller->addPoints($half, '장터 예약 취소 (50% 보상)');
            }

            $reservation->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $reservation->item->update(['status' => 'active']);

            return response()->json(['success' => true, 'message' => '예약이 취소되었습니다.']);
        });
    }
}
