<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MarketItem;
use App\Models\MarketReservation;
use App\Models\Notification;
use App\Models\PointLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketReservationController extends Controller
{
    /**
     * POST /api/market/{id}/reserve
     * Buyer reserves (holds) an item by putting down points as deposit.
     */
    public function reserve($itemId)
    {
        $item = MarketItem::findOrFail($itemId);
        $buyer = auth()->user();

        // Cannot reserve own item
        if ($item->user_id === $buyer->id) {
            return response()->json(['success' => false, 'message' => '자신의 물품은 찜할 수 없습니다.'], 403);
        }

        // Item must be active
        if ($item->status !== 'active') {
            return response()->json(['success' => false, 'message' => '현재 예약할 수 없는 상태입니다.'], 400);
        }

        // Check for existing pending reservation
        $existing = MarketReservation::where('market_item_id', $item->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            $msg = $existing->buyer_id === $buyer->id ? '이미 찜한 물품입니다.' : '다른 사용자가 이미 찜한 물품입니다.';
            return response()->json(['success' => false, 'message' => $msg], 400);
        }

        $pointsRequired = (int) ($item->reservation_points ?? 0);

        // Check buyer has enough points
        if ($pointsRequired > 0 && ($buyer->points ?? 0) < $pointsRequired) {
            return response()->json([
                'success' => false,
                'message' => "보증금이 부족합니다. 필요: {$pointsRequired}P, 보유: {$buyer->points}P",
            ], 400);
        }

        return DB::transaction(function () use ($item, $buyer, $pointsRequired) {
            // Deduct points from buyer
            if ($pointsRequired > 0) {
                $buyer->decrement('points', $pointsRequired);
                PointLog::create([
                    'user_id'       => $buyer->id,
                    'type'          => 'reservation_hold',
                    'action'        => 'spend',
                    'amount'        => -$pointsRequired,
                    'balance_after' => $buyer->fresh()->points,
                    'memo'          => "찜 보증금 ({$item->title})",
                ]);
            }

            // Create reservation
            $hours = (int) ($item->reservation_hours ?? 24);
            $reservation = MarketReservation::create([
                'market_item_id' => $item->id,
                'buyer_id'       => $buyer->id,
                'seller_id'      => $item->user_id,
                'points_held'    => $pointsRequired,
                'status'         => 'pending',
                'expires_at'     => now()->addHours($hours),
            ]);

            // Update item status
            $item->update(['status' => 'reserved']);

            // Notify seller
            Notification::create([
                'user_id' => $item->user_id,
                'type'    => 'market_reservation',
                'title'   => '찜 알림',
                'content'    => $buyer->name . '님이 "' . mb_substr($item->title, 0, 20) . '" 물품을 찜했습니다.',
                'url'     => '/market/' . $item->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => '찜이 완료되었습니다.',
                'data'    => $reservation->load(['buyer:id,name', 'seller:id,name']),
            ], 201);
        });
    }

    /**
     * POST /api/market/reservations/{id}/complete
     * Seller confirms deal - refund points to seller.
     */
    public function complete($id)
    {
        $reservation = MarketReservation::findOrFail($id);

        // Only buyer or seller
        if ($reservation->buyer_id !== auth()->id() && $reservation->seller_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => '권한이 없습니다.'], 403);
        }

        if ($reservation->status !== 'pending') {
            return response()->json(['success' => false, 'message' => '이미 처리된 찜입니다.'], 400);
        }

        return DB::transaction(function () use ($reservation) {
            // Return held points to seller (transaction complete = seller gets points)
            if ($reservation->points_held > 0) {
                $seller = User::find($reservation->seller_id);
                if ($seller) {
                    $seller->increment('points', $reservation->points_held);
                    PointLog::create([
                        'user_id'       => $seller->id,
                        'type'          => 'reservation_complete',
                        'action'        => 'earn',
                        'amount'        => $reservation->points_held,
                        'balance_after' => $seller->fresh()->points,
                        'memo'          => '거래 성사 보증금 수령',
                    ]);
                }
            }

            $reservation->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            // Mark item as sold
            $item = MarketItem::find($reservation->market_item_id);
            if ($item) {
                $item->update(['status' => 'sold']);
            }

            return response()->json([
                'success' => true,
                'message' => '거래가 완료되었습니다.',
                'data'    => $reservation,
            ]);
        });
    }

    /**
     * POST /api/market/reservations/{id}/cancel
     * Buyer cancels - points split 50/50.
     */
    public function cancel($id)
    {
        $reservation = MarketReservation::findOrFail($id);

        if ($reservation->buyer_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => '구매자만 찜을 취소할 수 있습니다.'], 403);
        }

        if ($reservation->status !== 'pending') {
            return response()->json(['success' => false, 'message' => '이미 처리된 찜입니다.'], 400);
        }

        return DB::transaction(function () use ($reservation) {
            if ($reservation->points_held > 0) {
                $buyerRefund = (int) floor($reservation->points_held / 2);
                $sellerShare = $reservation->points_held - $buyerRefund;

                // Refund 50% to buyer
                if ($buyerRefund > 0) {
                    $buyer = User::find($reservation->buyer_id);
                    if ($buyer) {
                        $buyer->increment('points', $buyerRefund);
                        PointLog::create([
                            'user_id'       => $buyer->id,
                            'type'          => 'reservation_cancel_refund',
                            'action'        => 'earn',
                            'amount'        => $buyerRefund,
                            'balance_after' => $buyer->fresh()->points,
                            'memo'          => '찜 취소 보증금 반환 (50%)',
                        ]);
                    }
                }

                // Give 50% to seller as penalty fee
                if ($sellerShare > 0) {
                    $seller = User::find($reservation->seller_id);
                    if ($seller) {
                        $seller->increment('points', $sellerShare);
                        PointLog::create([
                            'user_id'       => $seller->id,
                            'type'          => 'reservation_cancel_penalty',
                            'action'        => 'earn',
                            'amount'        => $sellerShare,
                            'balance_after' => $seller->fresh()->points,
                            'memo'          => '구매자 찜 취소 위약금 수령',
                        ]);
                    }
                }
            }

            $reservation->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
            ]);

            // Restore item to active
            $item = MarketItem::find($reservation->market_item_id);
            if ($item && $item->status === 'reserved') {
                $item->update(['status' => 'active']);
            }

            // Notify seller
            Notification::create([
                'user_id' => $reservation->seller_id,
                'type'    => 'market_reservation_cancel',
                'title'   => '찜 취소',
                'content'    => '찜이 취소되었습니다.',
                'url'     => '/market/' . $reservation->market_item_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => '찜이 취소되었습니다.',
                'data'    => $reservation,
            ]);
        });
    }
}
