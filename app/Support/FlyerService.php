<?php

namespace App\Support;

use App\Models\FlyerAd;
use App\Models\FlyerSlot;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 전단 광고 취소/반려/승인 시 슬롯 해제 + 포인트 환불.
 */
class FlyerService
{
    /**
     * 슬롯을 풀고(=다른 광고주가 다시 예약 가능) 해제한 슬롯 금액만큼 환불한다.
     *
     * @param bool $futureOnly true 면 이미 방송이 끝났거나 진행 중인 시간은 환불하지 않는다
     *                         (승인된 광고를 관리자가 내릴 때). false 면 전부(승인 전 반려/취소).
     * @return int 환불한 포인트
     */
    public static function release(FlyerAd $ad, bool $futureOnly, string $reason): int
    {
        return DB::transaction(function () use ($ad, $futureOnly, $reason) {
            $now = FlyerSchedule::now($ad->region_key);
            $slots = FlyerSlot::where('flyer_ad_id', $ad->id)->get();

            $refund = 0;
            $ids = [];
            foreach ($slots as $s) {
                if ($futureOnly) {
                    $isFuture = $s->slot_date->toDateString() > $now->toDateString()
                        || ($s->slot_date->toDateString() === $now->toDateString() && $s->slot_hour > $now->hour);
                    if (!$isFuture) continue;
                }
                $refund += (int) $s->price;
                $ids[] = $s->id;
            }
            if ($ids) FlyerSlot::whereIn('id', $ids)->delete();

            if ($refund > 0) {
                self::refund($ad->user_id, $refund, $reason, $ad->id);
            }
            return $refund;
        });
    }

    /** 승인 시점에 이미 지나간 시간은 방송되지 않았으므로 자동 환불 + 슬롯 삭제. 환불액 반환 */
    public static function refundExpiredSlots(FlyerAd $ad): int
    {
        return DB::transaction(function () use ($ad) {
            $now = FlyerSchedule::now($ad->region_key);
            $refund = 0;
            $ids = [];
            foreach (FlyerSlot::where('flyer_ad_id', $ad->id)->get() as $s) {
                $d = $s->slot_date->toDateString();
                $expired = $d < $now->toDateString() || ($d === $now->toDateString() && $s->slot_hour < $now->hour);
                if ($expired) { $refund += (int) $s->price; $ids[] = $s->id; }
            }
            if ($ids) FlyerSlot::whereIn('id', $ids)->delete();
            if ($refund > 0) self::refund($ad->user_id, $refund, '전단 광고 승인 전 지나간 시간 자동 환불', $ad->id);
            return $refund;
        });
    }

    /**
     * 환불은 "썼던 포인트를 돌려주는 것"이라 누적 획득량(lifetime_points, 회원 등급 기준)은
     * 올리지 않는다 — User::addPoints 는 양수면 lifetime 도 올리므로 직접 처리.
     */
    public static function refund(int $userId, int $amount, string $reason, int $flyerId): void
    {
        $user = User::whereKey($userId)->lockForUpdate()->first();
        if (!$user || $amount <= 0) return;
        $user->increment('points', $amount);
        $user->pointLogs()->create([
            'amount' => $amount,
            'type' => 'refund',
            'reason' => $reason,
            'balance_after' => $user->fresh()->points,
            'related_type' => FlyerAd::class,
            'related_id' => $flyerId,
        ]);
    }

    public static function notify(int $userId, string $title, string $content, int $flyerId): void
    {
        try {
            Notification::create([
                'user_id' => $userId, 'type' => 'flyer', 'title' => $title, 'content' => $content,
                'data' => ['flyer_id' => $flyerId],
            ]);
        } catch (\Throwable $e) {
            \Log::warning('전단 광고 알림 실패: ' . $e->getMessage());
        }
    }
}
