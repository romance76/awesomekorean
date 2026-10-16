<?php

namespace App\Support;

use App\Models\FlyerAd;
use App\Models\FlyerSlot;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 전단 광고 취소/반려/승인 시 슬롯 해제 + 환불.
 *
 * 결제 방식은 두 가지가 공존한다.
 *  - card   : 달러 카드 결제(현재). 신청 때 카드를 "보류"만 하고, 승인하면 청구, 반려/취소하면 보류 해제.
 *             total_price / slot.price 는 센트.
 *  - points : 예전에 포인트로 선결제한 건(기존 신청). 반려/취소하면 포인트 환불. total_price / slot.price 는 포인트.
 */
class FlyerService
{
    /** 카드 입력 중인 신청이 슬롯을 잡아두는 시간(분) — 지나면 자동으로 풀림 */
    public const CHECKOUT_MINUTES = 30;
    /** 카드 보류(Stripe)는 약 7일만 유지되므로, 그 전에 승인하지 못한 신청은 자동 취소 */
    public const HOLD_DAYS = 6;

    public static function isCard(FlyerAd $ad): bool
    {
        return $ad->payment_method === 'card';
    }

    /** 금액 표기: 카드는 $12.34, 포인트는 1,234P */
    public static function money(FlyerAd $ad, int $amount): string
    {
        return in_array($ad->payment_method, ['card', 'credit'], true) ? '$' . number_format($amount / 100, 2) : number_format($amount) . 'P';
    }

    /** 광고 잔액만으로 낸 신청 (카드 없이) */
    public static function isCredit(FlyerAd $ad): bool
    {
        return $ad->payment_method === 'credit';
    }

    /** 신청에 쓴 광고 잔액을 돌려준다 */
    private static function restoreCredit(FlyerAd $ad, int $cents, string $reason): void
    {
        if ($cents > 0) AdCredit::add($ad->user_id, $cents, 'restore', $reason, FlyerAd::class, $ad->id);
    }

    /** 슬롯을 "이미 지난 시간"과 "남은 시간"으로 나눈다 (슬롯의 주 기준 현지 시각). @return array{0:int[],1:int,2:int} [지난 슬롯 id, 지난 금액, 남은 금액] */
    private static function splitExpired(FlyerAd $ad): array
    {
        $ids = []; $expired = 0; $keep = 0;
        foreach (FlyerSlot::where('flyer_ad_id', $ad->id)->get() as $s) {
            $now = FlyerSchedule::now($s->region_key);
            $d = $s->slot_date->toDateString();
            if ($d < $now->toDateString() || ($d === $now->toDateString() && $s->slot_hour < $now->hour)) {
                $expired += (int) $s->price; $ids[] = $s->id;
            } else {
                $keep += (int) $s->price;
            }
        }
        return [$ids, $expired, $keep];
    }

    public static function payment(FlyerAd $ad): ?Payment
    {
        return $ad->payment_id ? Payment::find($ad->payment_id) : null;
    }

    /** 카드 입력만 하다 떠난 신청(awaiting_payment)이 잡고 있는 슬롯을 풀고 정리 */
    public static function purgeStale(): int
    {
        $stale = FlyerAd::where('status', 'awaiting_payment')
            ->where('created_at', '<', now()->subMinutes(self::CHECKOUT_MINUTES))->get();
        foreach ($stale as $ad) {
            self::abandon($ad, '결제를 마치지 않아 자동 취소');
        }
        return $stale->count();
    }

    /** 결제 전/직후 실패한 신청을 정리: 슬롯 해제 + 보류 해제 + 취소 처리 */
    public static function abandon(FlyerAd $ad, string $reason): void
    {
        DB::transaction(function () use ($ad, $reason) {
            FlyerSlot::where('flyer_ad_id', $ad->id)->delete();
            if ($p = self::payment($ad)) app(DirectPayments::class)->release($p);
            self::restoreCredit($ad, (int) $ad->credit_used_cents, "전면광고 신청이 취소되어 광고 잔액 반환: {$ad->title}");
            $ad->update(['status' => 'cancelled', 'reject_reason' => $reason, 'credit_used_cents' => 0, 'topup_cents' => 0]);
        });
    }

    /** 카드 보류 기간(6일)이 지나도록 승인되지 않은 대기 신청을 자동 취소(보류 해제) */
    public static function expireHolds(): int
    {
        $n = 0;
        $ads = FlyerAd::where('status', 'pending')->where('payment_method', 'card')->get();
        foreach ($ads as $ad) {
            $p = self::payment($ad);
            if (!$p || $p->status !== 'authorized' || $p->updated_at->gt(now()->subDays(self::HOLD_DAYS))) continue;
            self::abandon($ad, '승인이 지연되어 카드 보류가 만료되기 전에 자동 취소');
            self::notify($ad->user_id, '전면광고 신청 자동 취소', "'{$ad->title}' 신청이 오래 승인되지 않아 자동 취소되었어요. 카드에는 청구되지 않았습니다. 필요하면 다시 신청해주세요.", $ad->id);
            $n++;
        }
        return $n;
    }

    /**
     * 슬롯을 풀고(=다른 광고주가 다시 예약 가능) 돈을 돌려준다.
     *  - 카드·승인 전: 보류 해제(청구한 적 없음)    - 카드·게시 중: 남은 시간만큼 부분 환불
     *  - 포인트: 포인트 환불
     *
     * @param bool $futureOnly true 면 이미 방송이 끝났거나 진행 중인 시간은 돌려주지 않는다(승인된 광고를 내릴 때).
     * @return int 풀어준/환불한 금액 (카드=센트, 포인트=P) — 표기는 money()
     */
    public static function release(FlyerAd $ad, bool $futureOnly, string $reason): int
    {
        return DB::transaction(function () use ($ad, $futureOnly, $reason) {
            $slots = FlyerSlot::where('flyer_ad_id', $ad->id)->get();

            $amount = 0;
            $ids = [];
            foreach ($slots as $s) {
                if ($futureOnly) {
                    $now = FlyerSchedule::now($s->region_key);   // 추가 주는 시간대가 다를 수 있어 슬롯의 주 기준
                    $isFuture = $s->slot_date->toDateString() > $now->toDateString()
                        || ($s->slot_date->toDateString() === $now->toDateString() && $s->slot_hour > $now->hour);
                    if (!$isFuture) continue;
                }
                $amount += (int) $s->price;
                $ids[] = $s->id;
            }
            if ($ids) FlyerSlot::whereIn('id', $ids)->delete();

            if (self::isCard($ad)) {
                $p = self::payment($ad);
                if ($p) {
                    $dp = app(DirectPayments::class);
                    if (in_array($p->status, ['pending', 'authorized'], true)) {
                        // 아직 청구 전 → 보류만 풀고, 이 신청에 쓴 광고 잔액은 전부 돌려준다 (추가로 결제하려던 금액은 청구된 적 없음)
                        $dp->release($p);
                        self::restoreCredit($ad, (int) $ad->credit_used_cents, "전면광고 신청 취소·반려로 광고 잔액 반환: {$ad->title}");
                        $ad->update(['credit_used_cents' => 0, 'topup_cents' => 0]);
                    } elseif ($amount > 0) {
                        // 청구 후 남은 시간 환불: 광고 잔액으로 낸 몫은 잔액으로, 나머지는 카드로. 더 낸 금액(잔액으로 적립된 것)은 환불하지 않음
                        $C = (int) $ad->credit_used_cents;
                        $toCredit = min($amount, $C);
                        $toCard = $amount - $toCredit;
                        if ($toCredit > 0) {
                            self::restoreCredit($ad, $toCredit, "전면광고 게시 중단으로 광고 잔액 반환: {$ad->title}");
                            $ad->update(['credit_used_cents' => $C - $toCredit]);
                        }
                        if ($toCard > 0) $dp->refund($p, $toCard);
                    }
                } else {
                    self::restoreCredit($ad, (int) $ad->credit_used_cents, "전면광고 신청 취소·반려로 광고 잔액 반환: {$ad->title}");
                    $ad->update(['credit_used_cents' => 0, 'topup_cents' => 0]);
                }
            } elseif (self::isCredit($ad)) {
                // 광고 잔액으로만 낸 신청 — 풀어준 시간만큼 잔액으로 돌려준다
                $back = min($amount, (int) $ad->credit_used_cents);
                self::restoreCredit($ad, $back, "전면광고 신청 취소·반려·중단으로 광고 잔액 반환: {$ad->title}");
                $ad->update(['credit_used_cents' => max(0, (int) $ad->credit_used_cents - $back)]);
            } elseif ($amount > 0) {
                self::refund($ad->user_id, $amount, $reason, $ad->id);
            }
            return $amount;
        });
    }

    /**
     * (카드) 승인: 이미 지나간 시간을 뺀 금액만 청구하고, 그 시간대 슬롯은 정리한다.
     *  - 광고 잔액을 쓴 신청이면 카드로는 (광고 금액 − 쓴 잔액)만 청구, 지난 시간 때문에 남는 잔액은 되돌려준다.
     *  - 광고 금액보다 더 결제한 금액(topup)은 함께 청구하고, 청구가 끝나면 광고 잔액으로 적립한다.
     * 청구에 실패하면(보류 만료/카드 문제) 신청을 취소하고 예외를 던진다.
     *
     * @return array{captured:int,expired:int,remaining:int,price:int,topup:int,credit_used:int} 센트
     */
    public static function approveCard(FlyerAd $ad): array
    {
        $p = self::payment($ad);
        if (!$p || $p->status !== 'authorized') {
            throw new \DomainException('카드 보류가 확인되지 않은 신청이에요.');
        }
        [$expiredIds, $expired, $keep] = self::splitExpired($ad);
        if ($keep <= 0) {
            self::abandon($ad, '예약한 시간이 모두 지나 자동 취소');
            return ['captured' => 0, 'expired' => $expired, 'remaining' => 0, 'price' => 0, 'topup' => 0, 'credit_used' => 0];
        }
        $C = (int) $ad->credit_used_cents;
        $T = (int) $ad->topup_cents;
        $creditEff = min($C, $keep);
        $capture = ($keep - $creditEff) + $T;
        $captured = 0;
        try {
            $dp = app(DirectPayments::class);
            if ($capture >= CardLimits::STRIPE_MIN_CENTS) {
                $captured = $dp->capture($p, $capture);
            } else {
                $dp->release($p);   // 청구할 금액이 카드사 최소(50¢)에도 못 미치면 청구하지 않고 보류만 푼다
                $T = 0;
            }
        } catch (\Throwable $e) {
            \Log::error('전단 광고 카드 청구 실패: ' . $e->getMessage());
            self::abandon($ad, '카드 청구에 실패해 취소되었습니다');
            throw new \DomainException('카드 청구에 실패해서 신청을 취소했어요 (보류가 만료됐거나 카드에 문제가 있을 수 있어요).');
        }
        if ($expiredIds) FlyerSlot::whereIn('id', $expiredIds)->delete();
        if ($T > 0 && $captured > 0) {
            AdCredit::add($ad->user_id, $T, 'topup', "전면광고 결제 후 남은 금액 적립: {$ad->title}", FlyerAd::class, $ad->id);
        }
        self::restoreCredit($ad, $C - $creditEff, "전면광고 지난 시간만큼 광고 잔액 반환: {$ad->title}");
        $ad->update(['credit_used_cents' => $creditEff, 'topup_cents' => $T]);
        return ['captured' => $captured, 'expired' => $expired, 'remaining' => FlyerSlot::where('flyer_ad_id', $ad->id)->count(), 'price' => $keep, 'topup' => $T, 'credit_used' => $creditEff];
    }

    /** (광고 잔액만으로 낸 신청) 승인: 이미 지나간 시간만큼 잔액을 돌려주고 슬롯을 정리한다 */
    public static function approveCredit(FlyerAd $ad): array
    {
        [$expiredIds, $expired, $keep] = self::splitExpired($ad);
        if ($keep <= 0) {
            self::abandon($ad, '예약한 시간이 모두 지나 자동 취소');
            return ['expired' => $expired, 'remaining' => 0, 'price' => 0, 'credit_used' => 0];
        }
        $C = (int) $ad->credit_used_cents;
        $creditEff = min($C, $keep);
        if ($expiredIds) FlyerSlot::whereIn('id', $expiredIds)->delete();
        self::restoreCredit($ad, $C - $creditEff, "전면광고 지난 시간만큼 광고 잔액 반환: {$ad->title}");
        $ad->update(['credit_used_cents' => $creditEff]);
        return ['expired' => $expired, 'remaining' => FlyerSlot::where('flyer_ad_id', $ad->id)->count(), 'price' => $keep, 'credit_used' => $creditEff];
    }

    /** (포인트·기존 신청) 승인 시점에 이미 지나간 시간은 방송되지 않았으므로 자동 환불 + 슬롯 삭제. 환불액 반환 */
    public static function refundExpiredSlots(FlyerAd $ad): int
    {
        return DB::transaction(function () use ($ad) {
            $refund = 0;
            $ids = [];
            foreach (FlyerSlot::where('flyer_ad_id', $ad->id)->get() as $s) {
                $now = FlyerSchedule::now($s->region_key);
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
     * (포인트) 환불은 "썼던 포인트를 돌려주는 것"이라 누적 획득량(lifetime_points, 회원 등급 기준)은
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
