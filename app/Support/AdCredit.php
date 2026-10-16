<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * 광고 잔액(센트). 최소 결제 금액 때문에 더 냈거나 일부러 충전한 금액이 환불 없이 쌓이고, NEW 전면광고 신청 때 쓸 수 있다.
 * 잔액은 users.ad_credit_cents, 기록은 ad_credit_logs. 모든 변경은 사용자 행을 잠그고(lockForUpdate) 처리해 동시에 써도 어긋나지 않는다.
 */
class AdCredit
{
    public static function balance(int $userId): int
    {
        return (int) DB::table('users')->where('id', $userId)->value('ad_credit_cents');
    }

    /** 잔액 늘리기(적립/되돌림). 새 잔액 반환 */
    public static function add(int $userId, int $cents, string $type, string $reason, ?string $refType = null, ?int $refId = null): int
    {
        if ($cents <= 0) return self::balance($userId);
        return DB::transaction(function () use ($userId, $cents, $type, $reason, $refType, $refId) {
            $row = DB::table('users')->where('id', $userId)->lockForUpdate()->first(['id', 'ad_credit_cents']);
            if (!$row) return 0;
            $new = (int) $row->ad_credit_cents + $cents;
            DB::table('users')->where('id', $userId)->update(['ad_credit_cents' => $new]);
            self::log($userId, $cents, $new, $type, $reason, $refType, $refId);
            return $new;
        });
    }

    /** 잔액 쓰기. 모자라면 false (아무것도 바꾸지 않음) */
    public static function spend(int $userId, int $cents, string $reason, ?string $refType = null, ?int $refId = null): bool
    {
        if ($cents <= 0) return true;
        return DB::transaction(function () use ($userId, $cents, $reason, $refType, $refId) {
            $row = DB::table('users')->where('id', $userId)->lockForUpdate()->first(['id', 'ad_credit_cents']);
            if (!$row || (int) $row->ad_credit_cents < $cents) return false;
            $new = (int) $row->ad_credit_cents - $cents;
            DB::table('users')->where('id', $userId)->update(['ad_credit_cents' => $new]);
            self::log($userId, -$cents, $new, 'spend', $reason, $refType, $refId);
            return true;
        });
    }

    private static function log(int $userId, int $delta, int $after, string $type, string $reason, ?string $refType, ?int $refId): void
    {
        DB::table('ad_credit_logs')->insert([
            'user_id' => $userId, 'delta_cents' => $delta, 'balance_after' => $after, 'type' => $type,
            'reason' => mb_substr($reason, 0, 200), 'related_type' => $refType, 'related_id' => $refId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
