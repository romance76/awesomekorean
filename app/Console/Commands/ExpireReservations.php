<?php

namespace App\Console\Commands;

use App\Models\MarketReservation;
use App\Models\MarketItem;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire';
    protected $description = '만료된 찜 예약 처리 (매분 실행)';

    public function handle(): void
    {
        $expired = MarketReservation::where('status', 'pending')
            ->where('hold_until', '<', now())
            ->get();

        $count = 0;

        foreach ($expired as $reservation) {
            try {
                // 포인트를 판매자에게 이전 (노쇼 처리)
                if ($reservation->points_held > 0) {
                    $sellerWallet = DB::table('user_wallets')
                        ->where('user_id', $reservation->seller_id)
                        ->first();

                    if (!$sellerWallet) {
                        DB::table('user_wallets')->insert([
                            'user_id' => $reservation->seller_id,
                            'star_balance' => 0, 'gem_balance' => 0,
                            'coin_balance' => 0, 'chip_balance' => 0,
                            'lifetime_earned' => 0,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                        $sellerWallet = DB::table('user_wallets')
                            ->where('user_id', $reservation->seller_id)
                            ->first();
                    }

                    $sellerNewBalance = (int) $sellerWallet->coin_balance + $reservation->points_held;

                    DB::table('user_wallets')
                        ->where('user_id', $reservation->seller_id)
                        ->update([
                            'coin_balance' => DB::raw('coin_balance + ' . $reservation->points_held),
                            'lifetime_earned' => DB::raw('lifetime_earned + ' . $reservation->points_held),
                            'updated_at' => now(),
                        ]);

                    DB::table('wallet_transactions')->insert([
                        'user_id' => $reservation->seller_id,
                        'type' => 'reservation_expired',
                        'currency' => 'coin',
                        'amount' => $reservation->points_held,
                        'balance_after' => $sellerNewBalance,
                        'description' => '찜 만료 보증금 수령 (노쇼)',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // 상태를 expired로 변경
                $reservation->update(['status' => 'expired']);

                // 아이템 상태 복원
                $item = MarketItem::find($reservation->market_item_id);
                if ($item && $item->status === 'reserved') {
                    $item->update(['status' => 'active']);
                }

                // 구매자에게 알림
                Notification::create([
                    'user_id' => $reservation->buyer_id,
                    'type' => 'market_reservation_expired',
                    'title' => '찜 만료',
                    // Notification::$fillable엔 'content'만 있어(실제 컬럼명도 content)
                    // 'body' 키는 조용히 저장 안 되고 알림 본문이 항상 비어 있던 문제 수정.
                    'content' => '찜 유효시간이 지나 만료되었습니다.' . ($reservation->points_held > 0 ? " 보증금 {$reservation->points_held}P가 판매자에게 이전되었습니다." : ''),
                    'data' => json_encode(['item_id' => $reservation->market_item_id]),
                    'url' => '/market/' . $reservation->market_item_id,
                ]);

                // 판매자에게 알림
                Notification::create([
                    'user_id' => $reservation->seller_id,
                    'type' => 'market_reservation_expired',
                    'title' => '찜 만료',
                    'content' => '구매자가 연락하지 않아 찜이 만료되었습니다.' . ($reservation->points_held > 0 ? " 보증금 {$reservation->points_held}P가 지급되었습니다." : ''),
                    'data' => json_encode(['item_id' => $reservation->market_item_id]),
                    'url' => '/market/' . $reservation->market_item_id,
                ]);

                $count++;
            } catch (\Exception $e) {
                $this->error("Reservation #{$reservation->id} 처리 실패: {$e->getMessage()}");
            }
        }

        if ($count > 0) {
            $this->info("만료 찜 {$count}건 처리 완료.");
        }
    }
}
