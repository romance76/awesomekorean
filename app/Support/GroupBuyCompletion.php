<?php

namespace App\Support;

use App\Models\GroupBuy;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * 공동구매 완료·수락 — 총액 수수료 계산, 수락(수수료 결제 후 완료·쿠폰 발급), 수락하지 않고 종료.
 *
 *   총액   = 종료 시점 신청 수량 × 그 인원 구간의 표시 단가 (참가자가 개설자에게 낸 돈이 아니라 계산값)
 *   수수료 = GroupBuyTier::feeCents(총액)
 *   결제액 = 수수료 − 이미 낸 디파짓 (0 미만이면 0). 디파짓이 더 커도 남는 금액은 환불하지 않는다.
 *            결제액이 카드 최소 금액 미만이면 어썸코리안이 받지 않는다(면제).
 *   관리자·운영자가 연 공동구매는 수수료가 없다.
 */
class GroupBuyCompletion
{
    private const STAFF_ROLES = ['admin', 'super_admin', 'moderator'];

    public static function ownerIsStaff(GroupBuy $gb): bool
    {
        $role = \App\Models\User::whereKey($gb->user_id)->value('role');
        return in_array($role, self::STAFF_ROLES, true);
    }

    /** 살아 있는 신청(취소·환불되지 않은 것) */
    public static function activeParticipants(GroupBuy $gb)
    {
        return $gb->participants()->whereIn('status', ['pending', 'paid'])->get();
    }

    /** @return array{people:int,quantity:int,price_cents:int,total_cents:int,fee_cents:int,deposit_cents:int,due_cents:int,waived_cents:int,staff:bool} */
    public static function totals(GroupBuy $gb): array
    {
        $parts = self::activeParticipants($gb);
        $people = $parts->count();
        $qty = (int) $parts->sum('quantity');
        $price = (int) round(((float) $gb->current_price) * 100);
        $total = $qty * $price;
        $staff = self::ownerIsStaff($gb);
        $fee = $staff ? 0 : GroupBuyTier::feeCents($total);

        $deposit = 0;
        $pay = GroupBuyDeposit::payment($gb);
        if ($pay && $pay->status === 'captured') $deposit = (int) round($pay->amount * 100);

        $due = max(0, $fee - $deposit);
        $waived = 0;
        if ($due > 0 && $due < CardLimits::minCents()) { $waived = $due; $due = 0; }   // 카드 최소 금액 미만은 받지 않는다
        return ['people' => $people, 'quantity' => $qty, 'price_cents' => $price, 'total_cents' => $total, 'fee_cents' => $fee,
            'deposit_cents' => $deposit, 'due_cents' => $due, 'waived_cents' => $waived, 'staff' => $staff];
    }

    /** 수락할 수 없는 이유(문구). 가능하면 null */
    public static function ineligible(GroupBuy $gb): ?string
    {
        if (!in_array($gb->status, ['recruiting', 'confirmed'], true) || $gb->accepted_at) return '이미 끝났거나 수락할 수 없는 공동구매예요.';
        if (!$gb->is_approved) return '아직 승인되지 않은 공동구매예요.';
        $t = self::totals($gb);
        if ($t['people'] < 1) return '참가자가 아직 없어요.';
        $deadlinePassed = $gb->deadline && $gb->deadline->isPast();
        if (!$deadlinePassed && $gb->status !== 'confirmed' && $t['people'] < (int) $gb->min_participants) {
            return '마감일이 지났거나 최소 인원(' . (int) $gb->min_participants . '명)이 모이면 수락할 수 있어요.';
        }
        return null;
    }

    /** 완료 처리: 상태·금액 기록, 쿠폰 발급(쿠폰형), 참가자·개설자 알림 */
    public static function finalize(GroupBuy $gb, int $totalCents, int $feeCents): int
    {
        $issued = 0;
        DB::transaction(function () use ($gb, $totalCents, $feeCents, &$issued) {
            $gb->update(['status' => 'completed', 'accepted_at' => now(), 'total_cents' => $totalCents, 'fee_cents' => $feeCents]);
            $issued = GroupBuyCoupons::issueFor($gb);
        });

        try {
            $organizer = \App\Models\User::find($gb->user_id);
            if ($organizer) \App\Support\MilestonePoints::award($organizer, 'groupbuy_complete', GroupBuy::class, $gb->id, "공동구매 완료: {$gb->title}");
        } catch (\Throwable $e) { report($e); }

        $isCoupon = $gb->fulfillment === 'coupon';
        foreach (self::activeParticipants($gb) as $p) {
            GroupBuyNotify::send(
                (int) $p->user_id, 'groupbuy_completed', '참여한 공동구매가 완료되었어요',
                $isCoupon
                    ? "'{$gb->title}' 공동구매가 끝났어요. 마이페이지 \"내 쿠폰\"에서 쿠폰을 확인하세요. 동의하신 대로 이름·전화번호·이메일이 개설자에게 제공됐고, 개설자가 연락드릴 수 있어요."
                    : "'{$gb->title}' 공동구매가 끝났어요. 동의하신 대로 이름·전화번호·이메일이 개설자에게 제공됐고, 개설자가 연락드릴 거예요.",
                ['groupbuy_id' => $gb->id, 'url' => $isCoupon ? '/dashboard?tab=mycoupons' : '/groupbuy/' . $gb->id]
            );
        }
        GroupBuyNotify::send((int) $gb->user_id, 'groupbuy_completed', '공동구매가 완료되었어요', "'{$gb->title}' 공동구매가 완료됐어요." . ($isCoupon ? " 쿠폰 {$issued}장이 참가자에게 발급됐어요." : ' 마이페이지 "내 공동구매"에서 참가자 정보를 확인하세요.'), ['groupbuy_id' => $gb->id, 'url' => '/dashboard?tab=groupbuy']);
        return $issued;
    }

    /**
     * 조기 마감(수락하지 않고 종료): 마감일 전이든 후든, 사람이 덜 모였든 상관없이 모든 참여를 취소한다.
     * 참가자 포인트는 환불하고, 낸 디파짓은 돌려주지 않는다. 참가자(손님)에게는 "피치 못할 사정으로 취소됐다"는 안내를
     * 알림(+이메일)으로 반드시 보낸다. $note 는 개설자가 덧붙이는 한마디(선택).
     * @return int 안내를 보낸 참가자 수
     */
    public static function decline(GroupBuy $gb, ?string $note = null): int
    {
        $note = $note !== null ? trim($note) : null;
        $sent = 0;
        DB::transaction(function () use ($gb, $note, &$sent) {
            foreach ($gb->participants()->whereIn('status', ['pending', 'paid'])->with('user:id,email')->get() as $p) {
                $refunded = false;
                if ($p->status === 'paid' && $p->payment_type === 'point' && $p->paid_amount > 0 && $p->user) {
                    $p->user->addPoints($p->paid_amount, "공동구매 종료 환불: {$gb->title}", 'groupbuy_refund', ['type' => GroupBuy::class, 'id' => $gb->id]);
                    $refunded = true;
                }
                $p->update(['status' => 'refunded']);
                $text = "'{$gb->title}' 공동구매가 개설자의 피치 못할 사정으로 취소되었어요. 참여하신 내용은 모두 취소됐고" . ($refunded ? ' 낸 포인트는 돌려드렸어요.' : ' 결제하신 금액은 없어요.') . ' 불편을 드려 죄송해요.' . ($note ? "\n개설자의 말: {$note}" : '');
                GroupBuyNotify::send((int) $p->user_id, 'groupbuy_cancelled', '참여한 공동구매가 취소되었어요', $text, ['groupbuy_id' => $gb->id, 'url' => '/groupbuy/' . $gb->id]);
                GroupBuyNotify::mail($p->user->email ?? null, '[어썸코리안] 참여한 공동구매가 취소되었어요', $text);
                $sent++;
            }
            // 수수료 결제를 시작만 하고 끝내지 않은 카드 보류가 있으면 청구 없이 풀어 준다
            if ($gb->fee_payment_id && ($pay = Payment::find($gb->fee_payment_id))) {
                app(DirectPayments::class)->release($pay);
            }
            $gb->update(['status' => 'cancelled', 'closed_reason' => 'declined']);
        });
        return $sent;
    }
}
