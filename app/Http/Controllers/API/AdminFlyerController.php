<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\FlyerAd;
use App\Models\FlyerSlot;
use App\Support\FlyerSchedule;
use App\Support\FlyerService;
use Illuminate\Http\Request;

/**
 * NEW 전단 광고 관리자 — 승인 / 반려(전액 환불) / 게시 중 내리기(남은 시간 환불).
 */
class AdminFlyerController extends Controller
{
    /** GET /api/admin/flyers?status=pending|approved|rejected|cancelled|all */
    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');
        $q = FlyerAd::with('user:id,name,nickname,email')->orderByDesc('id');
        if ($status !== 'all') $q->where('status', $status);

        $page = $q->paginate(20);
        $page->getCollection()->transform(function (FlyerAd $ad) {
            $schedule = [];
            foreach (FlyerSlot::where('flyer_ad_id', $ad->id)->orderBy('slot_date')->orderBy('slot_hour')->get() as $s) {
                $schedule[$s->slot_date->toDateString()][] = $s->slot_hour;
            }
            $arr = $ad->toArray();
            $arr['tz'] = FlyerSchedule::timezone($ad->region_key);
            $arr['payment_status'] = FlyerService::payment($ad)?->status;
            $arr['schedule'] = collect($schedule)->map(fn($hours, $date) => ['date' => $date, 'hours' => $hours])->values();
            return $arr;
        });

        return response()->json(['success' => true, 'data' => $page, 'pending_count' => FlyerAd::where('status', 'pending')->count()]);
    }

    /** POST /api/admin/flyers/{id}/approve — 카드 건은 이때 실제 청구(이미 지나간 시간은 뺀 금액만) */
    public function approve($id)
    {
        $ad = FlyerAd::findOrFail($id);
        if ($ad->status !== 'pending') {
            return response()->json(['success' => false, 'message' => '승인 대기 중인 신청만 승인할 수 있어요.'], 422);
        }

        if (FlyerService::isCard($ad)) {
            try {
                $r = FlyerService::approveCard($ad);
            } catch (\DomainException $e) {
                FlyerService::notify($ad->user_id, '전면광고 신청 취소', "'{$ad->title}' 신청의 카드 결제를 처리하지 못해 취소되었어요. 카드에는 청구되지 않았습니다.", $ad->id);
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            if ($r['remaining'] === 0) {
                FlyerService::notify($ad->user_id, '전면광고 자동 취소', "예약한 시간이 모두 지나 승인 전에 만료되어 취소되었어요. 카드에는 청구되지 않았습니다.", $ad->id);
                return response()->json(['success' => true, 'message' => '예약 시간이 모두 지나 자동 취소했어요 (청구 없음).']);
            }
            $ad->update(['status' => 'approved', 'approved_at' => now(), 'reject_reason' => null, 'total_price' => $r['captured']]);
            $note = $r['expired'] ? ' (이미 지난 시간 ' . FlyerService::money($ad, $r['expired']) . ' 제외)' : '';
            FlyerService::notify($ad->user_id, '전면광고 승인', "'{$ad->title}' 전단이 승인되었어요. 예약한 시간에 NEW 게시판 상단에 방송됩니다. 카드에 " . FlyerService::money($ad, $r['captured']) . "가 청구되었어요.{$note}", $ad->id);
            return response()->json(['success' => true, 'message' => '승인했어요. ' . FlyerService::money($ad, $r['captured']) . ' 청구됨.' . $note]);
        }

        // ── 포인트로 선결제한 기존 신청 ──
        $ad->update(['status' => 'approved', 'approved_at' => now(), 'reject_reason' => null]);

        // 승인이 늦어 이미 지나간 시간은 방송되지 않았으므로 자동 환불
        $refund = FlyerService::refundExpiredSlots($ad);
        $remaining = FlyerSlot::where('flyer_ad_id', $ad->id)->count();
        if ($remaining === 0) {
            $ad->update(['status' => 'cancelled', 'reject_reason' => '예약한 시간이 모두 지나 자동 취소']);
            FlyerService::notify($ad->user_id, '전단 광고 자동 취소', "예약한 시간이 모두 지나 승인 전에 만료되어 {$refund}P가 환불되었어요.", $ad->id);
            return response()->json(['success' => true, 'message' => "예약 시간이 모두 지나 자동 취소 + {$refund}P 환불했어요."]);
        }

        FlyerService::notify($ad->user_id, '전단 광고 승인', "'{$ad->title}' 전단이 승인되었어요. 예약한 시간에 NEW 게시판 상단에 방송됩니다." . ($refund ? " (지나간 시간 {$refund}P 환불)" : ''), $ad->id);
        return response()->json(['success' => true, 'message' => '승인했어요.' . ($refund ? " 이미 지난 시간 {$refund}P는 자동 환불." : '')]);
    }

    /** POST /api/admin/flyers/{id}/reject { reason } — 대기 중이면 전액(카드: 청구 없이 보류 해제), 게시 중이면 남은 시간만 환불 */
    public function reject(Request $request, $id)
    {
        $request->validate(['reason' => 'nullable|string|max:200']);
        $ad = FlyerAd::findOrFail($id);
        if (!in_array($ad->status, ['pending', 'approved'], true)) {
            return response()->json(['success' => false, 'message' => '이미 처리된 신청이에요.'], 422);
        }

        $wasPending = $ad->status === 'pending';
        $reason = $request->input('reason') ?: '운영 정책에 맞지 않아 반려되었습니다.';
        try {
            $amount = FlyerService::release($ad, !$wasPending, "NEW 전단 광고 " . ($wasPending ? '반려' : '게시 중단') . " 환불: {$ad->title}");
        } catch (\Throwable $e) {
            \Log::error('전면광고 환불 실패: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => '환불 처리에 실패했어요. Stripe 대시보드에서 확인해주세요: ' . $e->getMessage()], 502);
        }
        $ad->update(['status' => 'rejected', 'reject_reason' => $reason]);

        $card = FlyerService::isCard($ad);
        $moneyNote = $card
            ? ($wasPending ? '카드에는 청구되지 않았습니다' : FlyerService::money($ad, $amount) . ' 환불')
            : FlyerService::money($ad, $amount) . ' 환불';
        FlyerService::notify($ad->user_id, $wasPending ? '전단 광고 반려' : '전단 광고 게시 중단', "'{$ad->title}' — {$reason} ({$moneyNote})", $ad->id);

        return response()->json(['success' => true, 'message' => "처리했어요. {$moneyNote}."]);
    }
}
