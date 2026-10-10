<?php

namespace App\Http\Controllers\API;

use App\Events\NewNotification;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Sweepstakes;
use App\Models\SweepstakesPrizeClaim;
use App\Support\SweepstakesPrizeClaimService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 경품 지급 관리 (최고관리자 전용)
 * - 당첨자별 상품 보내기: 디지털(링크/코드) → 쪽지+알림, 실물 → 배송 안내 쪽지+알림
 * - 지급 상태(대기/보냄/수령확인), 쓴 금액(장부), 처리 이력(로그)
 * 상품 링크(코드)는 이 컨트롤러 응답(관리자)과 당첨자 본인 쪽지에만 나타나며, 로그에는 코드를 남기지 않는다.
 */
class AdminPrizeDeliveryController extends Controller
{
    private function requireSuperAdmin()
    {
        if (auth()->user()->role !== 'super_admin') {
            abort(response()->json(['success' => false, 'message' => '경품 추첨 관리는 사이트 최고관리자만 접근할 수 있습니다'], 403));
        }
    }

    private function log(SweepstakesPrizeClaim $c, string $action, ?string $note = null, array $meta = []): void
    {
        DB::table('sweepstakes_delivery_logs')->insert([
            'claim_id' => $c->id, 'sweepstakes_id' => $c->sweepstakes_id, 'user_id' => $c->user_id,
            'actor_id' => auth()->id(), 'action' => $action, 'note' => $note,
            'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null, 'created_at' => now(),
        ]);
    }

    private function claimRow(SweepstakesPrizeClaim $c, ?Sweepstakes $s = null): array
    {
        $u = $c->user;
        $snap = $c->contact_snapshot;
        $cur = $u ? SweepstakesPrizeClaimService::contactStatus($u) : ['email' => '', 'phone' => '', 'address' => ''];
        $s = $s ?: $c->sweepstakes;
        return [
            'id' => $c->id, 'sweepstakes_id' => $c->sweepstakes_id, 'rank' => $c->rank,
            'prize_label' => $c->prize_label,
            'user_id' => $c->user_id,
            'nickname' => $u ? ($u->nickname ?: $u->name) : null,
            'name' => $snap['name'] ?? ($u->real_name ?? $u->name ?? null),
            'email' => $snap['email'] ?? $cur['email'],
            'phone' => $snap['phone'] ?? $cur['phone'],
            'address' => $snap['address'] ?? $cur['address'],
            'contact_confirmed_at' => optional($c->contact_confirmed_at)->toIso8601String(),
            'prize_type' => $c->prize_type ?: 'digital',
            'delivery_status' => $c->delivery_status ?: 'pending',
            'delivery_link' => $c->delivery_link,
            'cost_usd' => $c->cost_usd !== null ? (float) $c->cost_usd : null,
            'default_cost' => $s && $s->prize_value !== null ? (float) $s->prize_value : null,
            'sent_at' => optional($c->sent_at)->toIso8601String(),
            'delivery_confirmed_at' => optional($c->delivery_confirmed_at)->toIso8601String(),
            'notified_at' => optional($c->notified_at)->toIso8601String(),
        ];
    }

    /** 한 추첨의 당첨자 + 지급 현황 */
    public function claims(Sweepstakes $sweepstakes)
    {
        $this->requireSuperAdmin();
        SweepstakesPrizeClaimService::syncFor($sweepstakes);
        $rows = SweepstakesPrizeClaim::where('sweepstakes_id', $sweepstakes->id)->with('user')->orderBy('rank')->get()
            ->map(fn ($c) => $this->claimRow($c, $sweepstakes))->values();
        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** 당첨자에게 상품 보내기 (쪽지 + 알림) */
    public function deliver(Request $request, $id)
    {
        $this->requireSuperAdmin();
        $d = $request->validate([
            'prize_type' => 'required|in:digital,physical',
            'delivery_link' => 'nullable|string|max:2000',
            'message' => 'nullable|string|max:1500',
            'cost_usd' => 'nullable|numeric|min:0|max:100000',
            'resend' => 'nullable|boolean',
        ]);
        $claim = SweepstakesPrizeClaim::with(['user', 'sweepstakes'])->findOrFail($id);
        // 휴대폰에서 버튼을 두 번 눌러 쪽지·알림이 중복으로 나가고 링크가 덮어써지는 일 방지 — 다시 보내려면 resend 를 명시
        $already = in_array($claim->delivery_status, ['sent', 'confirmed'], true);
        if ($already && empty($d['resend'])) {
            return response()->json(['success' => false, 'code' => 'already_sent', 'message' => '이미 보낸 당첨자예요. 정말 다시 보내시려면 "다시 보내기"를 눌러 주세요.'], 409);
        }
        if (!$claim->user) {
            return response()->json(['success' => false, 'message' => '당첨자 계정을 찾을 수 없어요'], 422);
        }
        if ($d['prize_type'] === 'digital' && !trim((string) ($d['delivery_link'] ?? ''))) {
            return response()->json(['success' => false, 'message' => '디지털 상품은 링크나 코드를 입력해 주세요'], 422);
        }

        $s = $claim->sweepstakes;
        $prize = $claim->prize_label ?: (string) ($s->prize_name ?? '경품');
        $title = (string) ($s->title ?? '경품 추첨');
        $extra = trim((string) ($d['message'] ?? ''));
        if ($d['prize_type'] === 'digital') {
            $content = "🎉 [{$title}] 당첨 상품이 도착했어요!\n\n상품: {$prize}\n"
                . "상품 링크/코드: " . trim($d['delivery_link']) . "\n"
                . ($extra !== '' ? "\n{$extra}\n" : '')
                . "\n※ 이 링크(코드)는 본인 외에 공유하지 마세요. 사용 중 문제가 있으면 쪽지로 알려 주세요.";
            $action = 'digital_sent';
            $notifTitle = '🎁 당첨 상품이 도착했어요';
        } else {
            $content = "🎉 [{$title}] 당첨을 축하드려요!\n\n상품: {$prize}\n"
                . ($extra !== '' ? "\n{$extra}\n" : "\n상품 발송을 위해 받으실 주소와 연락처를 이 쪽지로 답장해 주세요.\n")
                . "\n※ 확인 후 영업일 기준 며칠 안에 발송해 드려요.";
            $action = 'physical_notice_sent';
            $notifTitle = '📦 당첨 상품 안내가 도착했어요';
        }

        DB::transaction(function () use ($claim, $d, $content, $action, $extra, $already) {
            $msg = Message::create(['sender_id' => auth()->id(), 'receiver_id' => $claim->user_id, 'content' => $content]);
            $claim->forceFill([
                'prize_type' => $d['prize_type'],
                'delivery_link' => $d['prize_type'] === 'digital' ? trim($d['delivery_link']) : null,
                'delivery_status' => 'sent',
                'sent_at' => now(), 'sent_by' => auth()->id(),
                'fulfilled_at' => $claim->fulfilled_at ?: now(),
                'cost_usd' => (array_key_exists('cost_usd', $d) && $d['cost_usd'] !== null) ? $d['cost_usd'] : $claim->cost_usd,
            ])->save();
            $this->log($claim, $already ? 'resent' : $action, $extra !== '' ? $extra : null, ['message_id' => $msg->id, 'prize_type' => $d['prize_type']]);
        });

        try {
            Notification::create([
                'user_id' => $claim->user_id, 'type' => 'message', 'title' => $notifTitle,
                'content' => "{$title} 경품 안내 쪽지가 도착했어요. 쪽지함을 확인해 주세요.",
                'data' => ['sender_id' => auth()->id(), 'sender_name' => '어썸코리안 운영팀'],
            ]);
            $unread = Notification::where('user_id', $claim->user_id)->whereNull('read_at')->count();
            broadcast(new NewNotification($claim->user_id, $unread, $notifTitle));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['success' => true, 'data' => $this->claimRow($claim->fresh(['user', 'sweepstakes']))]);
    }

    /** 상태·비용·상품 종류 수동 수정 (예: 직접 전달했음, 수령 확인, 쓴 금액 입력) */
    public function updateClaim(Request $request, $id)
    {
        $this->requireSuperAdmin();
        $d = $request->validate([
            'delivery_status' => 'nullable|in:pending,sent,confirmed',
            'prize_type' => 'nullable|in:digital,physical',
            'cost_usd' => 'nullable|numeric|min:0|max:100000',
            'note' => 'nullable|string|max:500',
        ]);
        $claim = SweepstakesPrizeClaim::with('user')->findOrFail($id);
        $changes = [];
        $logs = [];
        if (isset($d['prize_type']) && $d['prize_type'] !== ($claim->prize_type ?: 'digital')) {
            $changes['prize_type'] = $d['prize_type'];
            $logs[] = ['status_changed', ['field' => 'prize_type', 'value' => $d['prize_type']]];
        }
        if (array_key_exists('cost_usd', $d) && $d['cost_usd'] !== null && (float) $d['cost_usd'] !== (float) $claim->cost_usd) {
            $changes['cost_usd'] = $d['cost_usd'];
            $logs[] = ['cost_set', ['field' => 'cost_usd', 'value' => (float) $d['cost_usd']]];
        }
        if (isset($d['delivery_status']) && $d['delivery_status'] !== ($claim->delivery_status ?: 'pending')) {
            $st = $d['delivery_status'];
            $changes['delivery_status'] = $st;
            if ($st === 'sent' || $st === 'confirmed') {
                if (!$claim->sent_at) {
                    $changes['sent_at'] = now();
                    $changes['sent_by'] = auth()->id();
                }
                $changes['fulfilled_at'] = $claim->fulfilled_at ?: now();
            }
            $changes['delivery_confirmed_at'] = $st === 'confirmed' ? now() : null;
            $logs[] = ['status_changed', ['field' => 'delivery_status', 'value' => $st]];
        }
        if ($changes) {
            $claim->forceFill($changes)->save();
            foreach ($logs as [$action, $meta]) {
                $this->log($claim, $action, $d['note'] ?? null, $meta);
            }
        } elseif (!empty($d['note'])) {
            $this->log($claim, 'note', $d['note']);
        }
        return response()->json(['success' => true, 'data' => $this->claimRow($claim->fresh(['user', 'sweepstakes']))]);
    }

    /** 처리 이력: claim_id / sweepstakes_id 로 거르거나 전체 최근순 */
    public function logs(Request $request)
    {
        $this->requireSuperAdmin();
        $q = DB::table('sweepstakes_delivery_logs as l')
            ->leftJoin('users as w', 'w.id', '=', 'l.user_id')
            ->leftJoin('users as a', 'a.id', '=', 'l.actor_id')
            ->leftJoin('sweepstakes as s', 's.id', '=', 'l.sweepstakes_id')
            ->select('l.id', 'l.claim_id', 'l.sweepstakes_id', 'l.action', 'l.note', 'l.meta', 'l.created_at',
                's.title as sweepstakes_title',
                DB::raw('COALESCE(w.nickname, w.name) as winner_name'), DB::raw('COALESCE(a.nickname, a.name) as actor_name'))
            ->orderByDesc('l.id');
        if ($request->filled('claim_id')) {
            $q->where('l.claim_id', (int) $request->claim_id);
        }
        if ($request->filled('sweepstakes_id')) {
            $q->where('l.sweepstakes_id', (int) $request->sweepstakes_id);
        }
        $rows = $q->limit(min(300, max(10, (int) $request->input('limit', 100))))->get()->map(function ($r) {
            $r->created_at = $r->created_at ? \Carbon\Carbon::parse($r->created_at, 'UTC')->toIso8601String() : null;
            $r->meta = $r->meta ? json_decode($r->meta, true) : null;
            return $r;
        });
        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** 장부: 상품에 쓴 돈(총계·이번 달·월별) + 지급 현황 건수. 금액을 입력하지 않은 건은 상품 가치(prize_value)로 계산 */
    public function summary()
    {
        $this->requireSuperAdmin();
        $costExpr = 'COALESCE(c.cost_usd, s.prize_value, 0)';
        $base = fn () => DB::table('sweepstakes_prize_claims as c')->join('sweepstakes as s', 's.id', '=', 'c.sweepstakes_id');
        $sent = fn () => $base()->whereIn('c.delivery_status', ['sent', 'confirmed']);
        $monthStart = now('America/New_York')->startOfMonth()->utc();
        $total = (float) $sent()->sum(DB::raw($costExpr));
        $month = (float) $sent()->where('c.sent_at', '>=', $monthStart)->sum(DB::raw($costExpr));
        $estimated = (int) $sent()->whereNull('c.cost_usd')->count();   // 금액을 직접 입력하지 않아 상품 가치로 계산한 건수
        $byMonth = $sent()->whereNotNull('c.sent_at')
            ->selectRaw("DATE_FORMAT(CONVERT_TZ(c.sent_at,'+00:00','-04:00'),'%Y-%m') as ym, COUNT(*) as n, SUM({$costExpr}) as usd")
            ->groupBy('ym')->orderByDesc('ym')->limit(12)->get()
            ->map(fn ($r) => ['month' => $r->ym, 'count' => (int) $r->n, 'usd' => round((float) $r->usd, 2)])->values();
        $pendingQ = fn () => $base()->where(function ($q) {
            $q->whereNull('c.delivery_status')->orWhere('c.delivery_status', 'pending');
        });
        return response()->json(['success' => true, 'data' => [
            'total_usd' => round($total, 2), 'month_usd' => round($month, 2),
            'sent_count' => (int) $sent()->count(), 'estimated_count' => $estimated,
            'pending_count' => (int) $pendingQ()->count(), 'pending_usd' => round((float) $pendingQ()->sum(DB::raw($costExpr)), 2),
            'by_month' => $byMonth,
        ]]);
    }
}
