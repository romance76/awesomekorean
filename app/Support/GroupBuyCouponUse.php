<?php

namespace App\Support;

use App\Models\GroupBuy;
use App\Models\GroupBuyCoupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * 공동구매 쿠폰 사용 — 양쪽 동의(가게/개설자가 수락 요청 → 고객이 "맞다" 해야 사용 완료).
 *
 *   requestUse()    가게(개설자 로그인 또는 매장 PIN)가 사용 수락 → 고객에게 "맞나요?" (매장 3분 / 온라인 24시간)
 *   respond()       고객이 맞다/아니다 → 맞다: 사용 완료 / 아니다: 미사용으로 돌아가고 기록·알림, 반복되면 관리자에게 표시
 *   confirmByCode() 고객 폰이 안 될 때: 고객 확인 코드 4자리를 가게가 입력 → 사용 완료 (고객이 불러 준 것이 곧 동의)
 *   undo*()         사용 완료 뒤 10분 안에 양쪽이 동의하면 되돌림
 *   expire()/extend()/remind()  만료 정리, 개설자가 한 번 연장, 만료 3일 전 알림
 *
 * 모든 단계는 group_buy_coupon_events 에 남긴다(분쟁·감사용). 상태 바꿈은 행 잠금(lockForUpdate)으로 이중 처리를 막는다.
 */
class GroupBuyCouponUse
{
    public const STORE_SECONDS = 180;          // 매장: 고객 응답 3분
    public const ONLINE_SECONDS = 86400;       // 온라인: 24시간
    public const UNDO_MINUTES = 10;            // 사용 완료 뒤 되돌릴 수 있는 시간
    public const DENY_FLAG = 3;                // 이 횟수 이상 거절되면 관리자에게 표시
    public const MAX_EXTEND_DAYS = 90;

    /** @throws \DomainException 사용자에게 그대로 보여 줄 문구 */
    private static function fail(string $msg): never
    {
        throw new \DomainException($msg);
    }

    /** 현재 상태를 반영: 만료됐거나 확인 시간이 지난 쿠폰을 바로잡은 뒤 돌려준다(요청 처리 중 호출) */
    public static function refreshState(GroupBuyCoupon $c): GroupBuyCoupon
    {
        if (in_array($c->status, ['unused', 'pending'], true) && $c->expires_at && $c->expires_at->isPast()) {
            $c->update(['status' => 'expired', 'pending_mode' => null, 'pending_via' => null, 'pending_at' => null, 'pending_expires_at' => null]);
            GroupBuyCoupons::log($c, 'expired', null, 'system', '유효기간이 지남');
        } elseif ($c->status === 'pending' && $c->pending_expires_at && $c->pending_expires_at->isPast()) {
            $c->update(['status' => 'unused', 'pending_mode' => null, 'pending_via' => null, 'pending_at' => null, 'pending_expires_at' => null]);
            GroupBuyCoupons::log($c, 'request_timeout', null, 'system', '고객 응답 시간이 지나 요청이 취소됨');
        }
        return $c;
    }

    // ─── 가게 쪽 확인 ────────────────────────────────────────────────

    /** PIN 확인 시도 제한 키 */
    public static function pinLimitKey(int $groupBuyId, ?string $ip): string
    {
        return 'gb-pin:' . $groupBuyId . ':' . ($ip ?: 'x');
    }

    public static function checkPin(GroupBuy $gb, string $pin): bool
    {
        return $gb->store_pin_hash && Hash::check($pin, $gb->store_pin_hash);
    }

    /**
     * 사용 수락 요청 — 쿠폰을 pending 으로 바꾸고 고객에게 확인을 보낸다.
     * @param string $via  'login'(개설자 로그인) | 'pin'(매장 PIN)
     * @param string $mode 'store' | 'online'
     */
    public static function requestUse(GroupBuyCoupon $c, GroupBuy $gb, string $via, string $mode, ?int $actorId = null): GroupBuyCoupon
    {
        if ($mode === 'online' && $gb->use_mode === 'store') self::fail('이 공동구매는 매장에서만 쓸 수 있어요.');
        if ($mode === 'store' && $gb->use_mode === 'online') self::fail('이 공동구매는 온라인에서만 쓸 수 있어요.');

        $secs = $mode === 'online' ? self::ONLINE_SECONDS : self::STORE_SECONDS;
        DB::transaction(function () use ($c, $via, $mode, $secs, $actorId) {
            $row = GroupBuyCoupon::whereKey($c->id)->lockForUpdate()->first();
            self::refreshState($row);
            if ($row->status === 'used') self::fail('이미 사용된 쿠폰이에요.');
            if ($row->status === 'expired') self::fail('유효기간이 지난 쿠폰이에요.');
            if ($row->status === 'cancelled') self::fail('취소된 쿠폰이에요.');
            if ($row->status === 'pending' && $row->pending_expires_at && $row->pending_expires_at->isFuture()) self::fail('이미 고객에게 확인을 요청한 쿠폰이에요. 잠시 뒤에 다시 시도해 주세요.');
            $row->update([
                'status' => 'pending', 'pending_mode' => $mode, 'pending_via' => $via,
                'pending_at' => now(), 'pending_expires_at' => now()->addSeconds($secs),
            ]);
            GroupBuyCoupons::log($row, 'use_requested', $actorId, $via === 'pin' ? 'staff' : 'opener', $mode === 'online' ? '온라인 사용 요청' : '매장 사용 요청');
            $c->refresh();
        });

        $place = $mode === 'online' ? '온라인' : ($gb->use_place ?: '가게');
        $openerName = $gb->user ? ($gb->user->nickname ?: $gb->user->name) : '개설자';
        GroupBuyNotify::send(
            (int) $c->user_id, 'groupbuy_coupon_use', '쿠폰 사용 확인 요청이 왔어요',
            "'{$gb->title}' 쿠폰을 " . ($mode === 'online' ? '온라인에서' : "{$place}에서") . ' 사용합니다. 맞으면 앱에서 "맞아요"를 눌러 주세요. (' . ($mode === 'online' ? '24시간' : '3분') . ' 안에)',
            ['coupon_id' => $c->id, 'groupbuy_id' => $gb->id, 'url' => '/dashboard?tab=mycoupons']
        );
        if ($mode === 'online') {
            $email = \App\Models\User::whereKey($c->user_id)->value('email');
            GroupBuyNotify::mail($email, '[어썸코리안] 쿠폰 사용 확인 요청', "{$openerName}님이 '{$gb->title}' 쿠폰을 온라인에서 사용한다고 요청했어요.\n24시간 안에 어썸코리안 마이페이지 > 내 쿠폰에서 \"맞아요\" 또는 \"아니에요\"를 눌러 주세요.\n" . url('/dashboard?tab=mycoupons'));
        }
        return $c->fresh();
    }

    // ─── 고객 응답 ───────────────────────────────────────────────────

    public static function respond(GroupBuyCoupon $c, bool $accept, ?string $reason = null): GroupBuyCoupon
    {
        $gb = $c->groupBuy;
        DB::transaction(function () use ($c, $accept, $reason) {
            $row = GroupBuyCoupon::whereKey($c->id)->lockForUpdate()->first();
            self::refreshState($row);
            if ($row->status !== 'pending') self::fail($row->status === 'used' ? '이미 사용 처리된 쿠폰이에요.' : '확인을 기다리는 사용 요청이 없어요. (시간이 지났을 수 있어요)');
            if ($accept) {
                $row->update(['status' => 'used', 'used_at' => now(), 'used_mode' => $row->pending_mode, 'used_via' => 'holder',
                    'pending_mode' => null, 'pending_via' => null, 'pending_at' => null, 'pending_expires_at' => null,
                    'undo_requested_at' => null, 'undo_requested_by' => null]);
                GroupBuyCoupons::log($row, 'confirmed', $row->user_id, 'holder', '고객이 사용을 확인함');
            } else {
                $row->update(['status' => 'unused', 'denied_count' => $row->denied_count + 1,
                    'pending_mode' => null, 'pending_via' => null, 'pending_at' => null, 'pending_expires_at' => null]);
                GroupBuyCoupons::log($row, 'denied', $row->user_id, 'holder', $reason ?: '고객이 사용을 거절함');
            }
            $c->refresh();
        });

        if ($accept) {
            GroupBuyNotify::send((int) $gb->user_id, 'groupbuy_coupon_used', '쿠폰이 사용 처리됐어요', "'{$gb->title}' 쿠폰 " . $c->display_code . ' 사용을 손님이 확인했어요.', ['coupon_id' => $c->id, 'groupbuy_id' => $gb->id, 'url' => '/dashboard?tab=groupbuy']);
        } else {
            GroupBuyNotify::send((int) $gb->user_id, 'groupbuy_coupon_denied', '손님이 쿠폰 사용을 거절했어요', "'{$gb->title}' 쿠폰 " . $c->display_code . ' 사용을 손님이 거절했어요.' . ($reason ? " 이유: {$reason}" : ''), ['coupon_id' => $c->id, 'groupbuy_id' => $gb->id, 'url' => '/dashboard?tab=groupbuy']);
            self::flagIfRepeatedDenials($gb);
        }
        return $c->fresh();
    }

    /** 같은 공동구매에서 거절이 3번 이상 쌓이면 관리자에게 알린다(개설자의 허위 사용 요청 가능성) */
    private static function flagIfRepeatedDenials(GroupBuy $gb): void
    {
        $denied = (int) GroupBuyCoupon::where('group_buy_id', $gb->id)->sum('denied_count');
        if ($denied >= self::DENY_FLAG && $denied % self::DENY_FLAG === 0) {
            AdminNotifier::send(1, 'groupbuy_coupon_flag', '공동구매 쿠폰 사용 거절이 반복돼요', "'{$gb->title}' 공동구매에서 손님이 쿠폰 사용을 {$denied}번 거절했어요. 개설자의 허위 사용 요청인지 확인해 주세요.", '/admin/groupbuy', ['groupbuy_id' => $gb->id]);
        }
    }

    // ─── 고객 폰이 안 될 때: 확인 코드 4자리 ───────────────────────────

    public static function confirmByCode(GroupBuyCoupon $c, string $code, string $via, ?int $actorId = null): GroupBuyCoupon
    {
        $gb = $c->groupBuy;
        DB::transaction(function () use ($c, $code, $via, $actorId) {
            $row = GroupBuyCoupon::whereKey($c->id)->lockForUpdate()->first();
            self::refreshState($row);
            if ($row->status !== 'pending') self::fail('먼저 사용 수락을 눌러 주세요. 고객 확인 요청이 있어야 코드로 확인할 수 있어요.');
            if (!hash_equals((string) $row->confirm_code, (string) $code)) {
                GroupBuyCoupons::log($row, 'code_wrong', $actorId, $via === 'pin' ? 'staff' : 'opener', '확인 코드가 맞지 않음');
                self::fail('확인 코드가 맞지 않아요.');
            }
            $row->update(['status' => 'used', 'used_at' => now(), 'used_mode' => $row->pending_mode ?: 'store', 'used_via' => 'code',
                'pending_mode' => null, 'pending_via' => null, 'pending_at' => null, 'pending_expires_at' => null,
                'undo_requested_at' => null, 'undo_requested_by' => null]);
            GroupBuyCoupons::log($row, 'confirmed', $actorId, $via === 'pin' ? 'staff' : 'opener', '고객 확인 코드 4자리로 확인함');
            $c->refresh();
        });
        GroupBuyNotify::send((int) $c->user_id, 'groupbuy_coupon_used', '쿠폰이 사용 처리됐어요', "'{$gb->title}' 쿠폰 " . $c->display_code . ' 이(가) 확인 코드로 사용 처리됐어요. 본인이 한 게 아니라면 "문제 신고"를 눌러 주세요.', ['coupon_id' => $c->id, 'url' => '/dashboard?tab=mycoupons']);
        GroupBuyNotify::send((int) $gb->user_id, 'groupbuy_coupon_used', '쿠폰이 사용 처리됐어요', "'{$gb->title}' 쿠폰 " . $c->display_code . ' 사용을 확인했어요.', ['coupon_id' => $c->id, 'groupbuy_id' => $gb->id, 'url' => '/dashboard?tab=groupbuy']);
        return $c->fresh();
    }

    // ─── 실수 취소 (사용 완료 뒤 10분, 양쪽 동의) ───────────────────────

    /** 되돌릴 수 있는 시간이 남았는지 */
    public static function undoWindowLeft(GroupBuyCoupon $c): int
    {
        if ($c->status !== 'used' || !$c->used_at) return 0;
        $left = self::UNDO_MINUTES * 60 - $c->used_at->diffInSeconds(now(), false);
        return $left > 0 ? (int) ceil($left / 60) : 0;
    }

    /** @param string $who 'holder' | 'opener'  @param string $action 'request' | 'accept' | 'decline' */
    public static function undo(GroupBuyCoupon $c, string $who, string $action): string
    {
        $gb = $c->groupBuy;
        $msg = '';
        DB::transaction(function () use ($c, $who, $action, &$msg) {
            $row = GroupBuyCoupon::whereKey($c->id)->lockForUpdate()->first();
            if ($row->status !== 'used') self::fail('사용 완료된 쿠폰만 되돌릴 수 있어요.');
            if ($action === 'request') {
                if (self::undoWindowLeft($row) <= 0) self::fail('사용 후 ' . self::UNDO_MINUTES . '분이 지나 되돌릴 수 없어요. 문제가 있으면 신고해 주세요.');
                if ($row->undo_requested_by) self::fail('이미 되돌리기를 요청했어요.');
                $row->update(['undo_requested_at' => now(), 'undo_requested_by' => $who]);
                GroupBuyCoupons::log($row, 'undo_requested', null, $who, '사용 취소(되돌리기) 요청');
                $msg = '되돌리기를 요청했어요. 상대가 동의하면 쿠폰이 다시 살아나요.';
            } else {
                $other = $who === 'holder' ? 'opener' : 'holder';
                if ($row->undo_requested_by !== $other) self::fail('동의할 되돌리기 요청이 없어요.');
                if ($action === 'accept') {
                    $row->update(['status' => 'unused', 'used_at' => null, 'used_mode' => null, 'used_via' => null, 'undo_requested_at' => null, 'undo_requested_by' => null]);
                    GroupBuyCoupons::log($row, 'undone', null, $who, '양쪽 동의로 사용이 취소됨');
                    $msg = '사용이 취소되어 쿠폰이 다시 쓸 수 있게 됐어요.';
                } else {
                    $row->update(['undo_requested_at' => null, 'undo_requested_by' => null]);
                    GroupBuyCoupons::log($row, 'undo_declined', null, $who, '되돌리기 요청을 거절함');
                    $msg = '되돌리기를 거절했어요.';
                }
            }
            $c->refresh();
        });
        $notifyTo = $who === 'holder' ? (int) $gb->user_id : (int) $c->user_id;
        if ($action === 'request') {
            GroupBuyNotify::send($notifyTo, 'groupbuy_coupon_undo', '쿠폰 사용 취소 요청이 왔어요', "'{$gb->title}' 쿠폰 " . $c->display_code . ' 의 사용 취소(되돌리기)를 요청했어요. 동의하거나 거절해 주세요.', ['coupon_id' => $c->id, 'groupbuy_id' => $gb->id, 'url' => $who === 'holder' ? '/dashboard?tab=groupbuy' : '/dashboard?tab=mycoupons']);
        }
        return $msg;
    }

    // ─── 만료·연장·알림 (점검 작업) ────────────────────────────────────

    /** 만료 정리 + 응답 시간이 지난 확인 요청 취소 + 만료 3일 전 알림. @return array{expired:int,timeouts:int,reminded:int} */
    public static function maintenance(): array
    {
        $expired = 0; $timeouts = 0; $reminded = 0;
        foreach (GroupBuyCoupon::whereIn('status', ['unused', 'pending'])->where('expires_at', '<', now())->get() as $c) {
            self::refreshState($c); $expired++;
        }
        foreach (GroupBuyCoupon::where('status', 'pending')->where('pending_expires_at', '<', now())->get() as $c) {
            self::refreshState($c); $timeouts++;
        }
        $soon = GroupBuyCoupon::where('status', 'unused')->whereNull('reminder_sent_at')
            ->where('expires_at', '>', now())->where('expires_at', '<', now()->addDays(3))->with('groupBuy:id,title')->get();
        $byUser = $soon->groupBy('user_id');
        foreach ($byUser as $uid => $list) {
            $title = $list->first()->groupBuy->title ?? '공동구매';
            GroupBuyNotify::send((int) $uid, 'groupbuy_coupon_expiring', '쿠폰이 곧 만료돼요', "'{$title}' 등 쿠폰 {$list->count()}장이 3일 안에 만료돼요. 마이페이지 > 내 쿠폰에서 확인하세요.", ['url' => '/dashboard?tab=mycoupons']);
            GroupBuyCoupon::whereIn('id', $list->pluck('id'))->update(['reminder_sent_at' => now()]);
            $reminded += $list->count();
        }
        return ['expired' => $expired, 'timeouts' => $timeouts, 'reminded' => $reminded];
    }

    /** 개설자가 유효기간을 한 번 연장 — 아직 쓰지 않은(미사용·확인 중) 쿠폰 전부에 +N일. @return int 연장한 쿠폰 수 */
    public static function extend(GroupBuy $gb, int $days): int
    {
        if ($gb->coupon_extended_at) self::fail('유효기간은 한 번만 연장할 수 있어요.');
        if ($days < 1 || $days > self::MAX_EXTEND_DAYS) self::fail('연장은 1~' . self::MAX_EXTEND_DAYS . '일까지 할 수 있어요.');
        $n = 0;
        DB::transaction(function () use ($gb, $days, &$n) {
            // 막 만료돼 정리되기 전인 쿠폰(3일 안)도 되살린다 — 연장은 "아직 못 쓴 쿠폰"을 위한 것
            $list = GroupBuyCoupon::where('group_buy_id', $gb->id)
                ->where(fn ($q) => $q->whereIn('status', ['unused', 'pending'])->orWhere(fn ($w) => $w->where('status', 'expired')->where('expires_at', '>', now()->subDays(3))))
                ->get();
            foreach ($list as $c) {
                $base = ($c->expires_at && $c->expires_at->isFuture()) ? $c->expires_at : now();
                $c->update(['expires_at' => $base->copy()->addDays($days), 'status' => $c->status === 'expired' ? 'unused' : $c->status, 'reminder_sent_at' => null]);
                GroupBuyCoupons::log($c, 'extended', $gb->user_id, 'opener', "+{$days}일 연장");
                GroupBuyNotify::send((int) $c->user_id, 'groupbuy_coupon_extended', '쿠폰 유효기간이 연장됐어요', "'{$gb->title}' 쿠폰의 유효기간이 {$days}일 연장됐어요.", ['coupon_id' => $c->id, 'url' => '/dashboard?tab=mycoupons']);
                $n++;
            }
            $gb->update(['coupon_extended_at' => now()]);
        });
        return $n;
    }
}
