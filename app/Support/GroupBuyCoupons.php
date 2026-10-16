<?php

namespace App\Support;

use App\Models\GroupBuy;
use App\Models\GroupBuyCoupon;
use App\Models\GroupBuyCouponEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 공동구매 쿠폰 — 발급과 공통 도구. 사용(양쪽 동의) 흐름은 GroupBuyCouponUse.
 * 쿠폰은 "이 공동구매 가격으로 살 수 있는 권리"이고, 돈은 쓸 때 개설자에게 직접 낸다(어썸코리안은 돈을 보관하지 않는다).
 */
class GroupBuyCoupons
{
    /** 헷갈리는 글자(0 O 1 I L)를 뺀 쿠폰 번호 글자 */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 10; $i++) $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        } while (GroupBuyCoupon::where('code', $code)->exists());
        return $code;
    }

    /** 사람이 친 쿠폰 번호를 정리: 공백·하이픈 제거, 대문자 */
    public static function normalizeCode($raw): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $raw));
    }

    /** 쿠폰 만료 시각: 오늘부터 N일, 그날 밤(미국 동부 기준)까지 */
    public static function expiryFor(int $days): Carbon
    {
        return Carbon::now('America/New_York')->addDays(max(1, $days))->endOfDay()->utc();
    }

    public static function log(GroupBuyCoupon $c, string $type, ?int $actorId, string $kind, ?string $note = null): void
    {
        GroupBuyCouponEvent::create([
            'coupon_id' => $c->id, 'type' => $type, 'actor_id' => $actorId, 'actor_kind' => $kind,
            'note' => $note !== null ? mb_substr($note, 0, 300) : null, 'created_at' => now(),
        ]);
    }

    /**
     * 완료된 쿠폰형 공동구매의 쿠폰 발급 — 참가자가 산 수량만큼 한 장씩.
     * @return int 발급한 쿠폰 수
     */
    public static function issueFor(GroupBuy $gb): int
    {
        if ($gb->fulfillment !== 'coupon') return 0;
        $expires = self::expiryFor((int) ($gb->coupon_valid_days ?: 30));
        $n = 0;
        $parts = $gb->participants()->whereIn('status', ['pending', 'paid'])->get();
        DB::transaction(function () use ($parts, $gb, $expires, &$n) {
            foreach ($parts as $p) {
                // 이미 발급된 참가자는 건너뜀(다시 호출돼도 중복 발급되지 않게)
                if (GroupBuyCoupon::where('participant_id', $p->id)->exists()) continue;
                for ($i = 1; $i <= max(1, (int) $p->quantity); $i++) {
                    $c = GroupBuyCoupon::create([
                        'group_buy_id' => $gb->id, 'participant_id' => $p->id, 'user_id' => $p->user_id, 'seq' => $i,
                        'code' => self::newCode(), 'confirm_code' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                        'status' => 'unused', 'expires_at' => $expires,
                    ]);
                    self::log($c, 'issued', null, 'system', '공동구매 완료로 발급');
                    $n++;
                }
            }
        });
        return $n;
    }
}
