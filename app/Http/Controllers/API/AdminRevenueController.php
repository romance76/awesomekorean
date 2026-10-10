<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 관리자 "매출/결제 현황" — 포인트 구매(카드로 포인트를 산 돈)와 달러 직접 결제
 * (경품 이벤트 의뢰, NEW 전면광고)를 구분해서 보여주고 전체 결제액도 합산한다.
 *
 * 매출로 치는 금액(net):
 *  - 포인트 구매: status=completed 의 amount        (환불되면 status=refunded 라 제외)
 *  - 직접 결제:   status=captured/refunded 의 amount - refunded_amount   (청구된 뒤 남은 금액)
 * 카드 보류만 잡혀 있는 금액(authorized)은 아직 매출이 아니라 "보류 중"으로 따로 보여준다.
 * 기간 경계는 미국 동부시간(ET) 기준 날짜로 자른다.
 */
class AdminRevenueController extends Controller
{
    private const TZ = 'America/New_York';

    private const NET_SQL = "CASE
        WHEN kind = 'points' AND status = 'completed' THEN amount
        WHEN kind <> 'points' AND status IN ('captured','refunded') THEN amount - refunded_amount
        ELSE 0 END";

    /** @return array{0:Carbon|null,1:Carbon|null} UTC 시작/끝(끝은 미포함) */
    private function range(Request $r): array
    {
        $now = Carbon::now(self::TZ);
        switch ($r->input('range', '30d')) {
            case 'today': $from = $now->copy()->startOfDay(); $to = $from->copy()->addDay(); break;
            case '7d':    $from = $now->copy()->subDays(6)->startOfDay(); $to = $now->copy()->startOfDay()->addDay(); break;
            case 'month': $from = $now->copy()->startOfMonth(); $to = $from->copy()->addMonth(); break;
            case 'year':  $from = $now->copy()->startOfYear(); $to = $from->copy()->addYear(); break;
            case 'all':   return [null, null];
            case 'custom':
                // 잘못된 날짜(abc 등)는 500 대신 안내 메시지, 시작일이 끝일보다 늦으면 자동으로 바꿔 준다
                try {
                    $a = Carbon::parse($r->input('from', $now->toDateString()), self::TZ)->startOfDay();
                    $b = Carbon::parse($r->input('to', $now->toDateString()), self::TZ)->startOfDay();
                } catch (\Throwable $e) {
                    abort(response()->json(['success' => false, 'message' => '날짜 형식이 올바르지 않아요 (예: 2026-10-01)'], 422));
                }
                if ($a->gt($b)) { [$a, $b] = [$b, $a]; }
                $from = $a;
                $to = $b->copy()->addDay();
                break;
            default:      $from = $now->copy()->subDays(29)->startOfDay(); $to = $now->copy()->startOfDay()->addDay();
        }
        return [$from->copy()->utc(), $to->copy()->utc()];
    }

    private function base(?Carbon $from, ?Carbon $to)
    {
        $q = Payment::query();
        if ($from) $q->where('payments.created_at', '>=', $from);
        if ($to)   $q->where('payments.created_at', '<', $to);
        return $q;
    }

    /** GET /admin/revenue/summary */
    public function summary(Request $r)
    {
        [$from, $to] = $this->range($r);

        $byKind = $this->base($from, $to)
            ->selectRaw('kind, COUNT(CASE WHEN ' . self::NET_SQL . ' > 0 THEN 1 END) AS paid_count, SUM(' . self::NET_SQL . ') AS net')
            ->groupBy('kind')->get()->keyBy('kind');

        $row = fn ($k) => ['net' => round((float) ($byKind[$k]->net ?? 0), 2), 'count' => (int) ($byKind[$k]->paid_count ?? 0)];
        $points = $row(Payment::KIND_POINTS);
        $kinds = [
            Payment::KIND_EVENT_REQUEST => $row(Payment::KIND_EVENT_REQUEST),
            Payment::KIND_FLYER => $row(Payment::KIND_FLYER),
        ];
        $directNet = round($kinds['event_request']['net'] + $kinds['flyer']['net'], 2);
        $directCount = $kinds['event_request']['count'] + $kinds['flyer']['count'];

        // 보류 중(승인 대기)인 카드 금액 — 기간과 무관하게 지금 걸려 있는 전체
        $held = Payment::where('status', 'authorized')->selectRaw('COUNT(*) c, COALESCE(SUM(amount),0) s')->first();

        // 환불 합계(기간 내 생성분)
        $refund = $this->base($from, $to)->selectRaw(
            "COALESCE(SUM(CASE WHEN kind='points' AND status='refunded' THEN amount WHEN kind<>'points' THEN refunded_amount ELSE 0 END),0) s"
        )->value('s');

        // 일별 추이 (ET 날짜 기준)
        $series = [];
        $days = 0;
        if ($from && $to) {
            $days = min(400, (int) $from->diffInDays($to));
            $rows = $this->base($from, $to)
                ->selectRaw('payments.created_at AS at, kind, ' . self::NET_SQL . ' AS net')
                ->get();
            $map = [];
            foreach ($rows as $x) {
                if ((float) $x->net <= 0) continue;
                $d = Carbon::parse($x->at, 'UTC')->setTimezone(self::TZ)->toDateString();
                $isPoints = $x->kind === Payment::KIND_POINTS;
                $map[$d][$isPoints ? 'points' : 'direct'] = ($map[$d][$isPoints ? 'points' : 'direct'] ?? 0) + (float) $x->net;
            }
            $cur = $from->copy()->setTimezone(self::TZ)->startOfDay();
            for ($i = 0; $i < $days; $i++, $cur->addDay()) {
                $d = $cur->toDateString();
                $series[] = ['date' => $d, 'points' => round($map[$d]['points'] ?? 0, 2), 'direct' => round($map[$d]['direct'] ?? 0, 2)];
            }
        }

        return response()->json(['success' => true, 'data' => [
            'range' => $r->input('range', '30d'),
            'total' => ['net' => round($points['net'] + $directNet, 2), 'count' => $points['count'] + $directCount],
            'points' => $points,                       // 포인트 구매(현금으로 포인트를 산 금액)
            'direct' => ['net' => $directNet, 'count' => $directCount, 'by_kind' => $kinds],  // 달러 직접 결제
            'held' => ['amount' => round((float) $held->s, 2), 'count' => (int) $held->c],
            'refunded' => round((float) $refund, 2),
            'series' => $series,
        ]]);
    }

    /** GET /admin/revenue/list — 결제 내역 (kind, status, 검색, 기간) */
    public function list(Request $r)
    {
        [$from, $to] = $this->range($r);
        $q = $this->base($from, $to)->with('user:id,name,nickname,email')->orderByDesc('payments.created_at');

        $kind = $r->input('kind');
        if ($kind === 'direct') $q->whereIn('kind', Payment::DIRECT_KINDS);
        elseif ($kind) $q->where('kind', $kind);
        if ($r->filled('status')) $q->where('status', $r->status);
        if ($r->filled('search')) {
            $s = $r->search;
            $q->where(fn ($w) => $w->whereHas('user', fn ($u) => $u->where('name', 'like', "%$s%")->orWhere('nickname', 'like', "%$s%")->orWhere('email', 'like', "%$s%"))
                ->orWhere('stripe_payment_id', 'like', "%$s%")->orWhere('description', 'like', "%$s%"));
        }

        \App\Support\AdminSort::apply($q, $r, [
            'created_at' => 'payments.created_at', 'kind' => 'payments.kind',
            'payer' => fn ($q, $d) => $q->orderBy(\App\Models\User::select('name')->whereColumn('users.id', 'payments.user_id'), $d),
            'description' => 'payments.description', 'amount' => 'payments.amount', 'status' => 'payments.status',
            'id' => 'payments.id',
        ], 'payments.id');

        $page = $q->paginate(30);
        $page->getCollection()->transform(function (Payment $p) {
            $p->net = match (true) {
                $p->kind === Payment::KIND_POINTS => $p->status === 'completed' ? (float) $p->amount : 0.0,
                in_array($p->status, ['captured', 'refunded']) => (float) $p->amount - (float) $p->refunded_amount,
                default => 0.0,
            };
            return $p;
        });
        return response()->json(['success' => true, 'data' => $page]);
    }
}
