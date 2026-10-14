<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\EntryTransaction;
use App\Models\Payment;
use App\Models\PointLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 지급·보상 센터(관리자) — 포인트·Entry 직접 지급은 기존 API(/users/{id}/points, /entries/adjust)를 그대로 쓰고,
 * 여기서는 ① 실제로 받은 구매 금액 직접 기록(최고관리자 + 비밀번호 재확인), ② 최근 지급 기록(포인트·Entry·직접 기록 결제)을 맡는다.
 */
class AdminGrantController extends Controller
{
    /** 직접 기록할 수 있는 결제 종류 (카드 결제 종류와 같은 이름 → 매출·인보이스에 그대로 잡힘) */
    private const KINDS = [Payment::KIND_BANNER, Payment::KIND_TEXT_AD, Payment::KIND_PROMOTION, Payment::KIND_FLYER, Payment::KIND_EVENT_REQUEST];

    /** POST /admin/grants/manual-payment — 관리자가 직접 받은 돈(Zelle·현금·수표 등)을 결제로 기록하고 인보이스 발행 */
    public function manualPayment(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'kind' => 'required|in:' . implode(',', self::KINDS),
            'amount' => 'required|numeric|min:0.5|max:10000',
            'description' => 'required|string|min:2|max:150',
            'method' => 'required|in:zelle,cash,check,other',
            'memo' => 'nullable|string|max:120',
            'direction' => 'nullable|in:add,subtract',
        ], ['amount.min' => '금액은 $0.50 이상이어야 해요.', 'amount.max' => '한 번에 $10,000 까지 기록할 수 있어요.', 'description.required' => '무엇을 구매했는지 적어 주세요.']);

        if (!Schema::hasColumn('payments', 'pay_method')) {
            return response()->json(['success' => false, 'message' => '아직 준비 중이에요. 잠시 뒤 다시 시도해 주세요.'], 503);
        }

        $methods = ['zelle' => 'Zelle', 'cash' => '현금(Cash)', 'check' => '수표(Check)', 'other' => '기타'];
        $note = trim($methods[$data['method']] . (!empty($data['memo']) ? ' · ' . $data['memo'] : ''));
        $amount = round((float) $data['amount'], 2);
        // 차감: 잘못 기록했거나 돌려준 돈을 "마이너스 금액" 기록으로 남긴다. 매출·누적 구매에서 그만큼 빠지고 인보이스는 만들지 않는다.
        // (status 'refunded' + 음수 금액 → Revenue::NET_SQL 이 amount 를 그대로 합산, 인보이스 훅은 인보이스가 없어 아무것도 안 함)
        $subtract = ($data['direction'] ?? 'add') === 'subtract';
        if ($subtract) $note = '차감 · ' . $note;

        $payment = Payment::create([
            'user_id' => (int) $data['user_id'],
            'kind' => $data['kind'],
            'amount' => $subtract ? -$amount : $amount,
            'currency' => 'USD',
            'description' => $subtract ? ('차감: ' . $data['description']) : $data['description'],
            'status' => $subtract ? 'refunded' : 'captured',   // 지급: 받은 돈 → 매출에 잡히고 인보이스 자동 발행
            'captured_at' => now(),
            'pay_method' => 'manual',
            'pay_note' => mb_substr($note, 0, 200),
        ]);

        $this->audit($request, ($subtract ? 'MANUAL_PAYMENT_SUBTRACT payments/' : 'MANUAL_PAYMENT payments/') . $payment->id, 'payments', $payment->id, [
            'reason' => $note, 'amount_usd' => $subtract ? -$amount : $amount, 'kind' => $data['kind'], 'user_id' => (int) $data['user_id'], 'description' => $data['description'],
        ]);

        if ($subtract) {
            return response()->json(['success' => true, 'message' => '$' . number_format($amount, 2) . ' 를 차감했어요. 매출과 누적 구매에서 빠져요.', 'data' => ['payment_id' => $payment->id, 'invoice_no' => null]], 201);
        }
        $invoiceNo = null;
        try { $invoiceNo = \App\Models\Invoice::where('payment_id', $payment->id)->value('invoice_no'); } catch (\Throwable $e) {}
        return response()->json([
            'success' => true,
            'message' => '$' . number_format($amount, 2) . ' 결제를 기록했어요.' . ($invoiceNo ? " 인보이스 {$invoiceNo} 가 발행됐어요." : ''),
            'data' => ['payment_id' => $payment->id, 'invoice_no' => $invoiceNo],
        ], 201);
    }

    /** GET /admin/grants/recent — 최근 관리자 지급 기록(포인트·Entry·직접 기록 결제), 최신순 60건 */
    public function recent(Request $request)
    {
        $rows = collect();

        $points = PointLog::whereIn('type', ['admin', 'admin_adjust'])->orderByDesc('id')->limit(40)->get(['id', 'user_id', 'amount', 'reason', 'created_at']);
        foreach ($points as $p) $rows->push(['kind' => 'points', 'id' => $p->id, 'user_id' => $p->user_id, 'amount' => (int) $p->amount, 'label' => (string) $p->reason, 'at' => $p->created_at]);

        $entries = EntryTransaction::where('transaction_type', 'ADMIN_ADJUSTMENT')->orderByDesc('id')->limit(40)->get(['id', 'user_id', 'amount', 'description', 'created_at']);
        foreach ($entries as $e) $rows->push(['kind' => 'entries', 'id' => $e->id, 'user_id' => $e->user_id, 'amount' => (int) $e->amount, 'label' => (string) $e->description, 'at' => $e->created_at]);

        if (Schema::hasColumn('payments', 'pay_method')) {
            $pays = Payment::where('pay_method', 'manual')->orderByDesc('id')->limit(40)->get(['id', 'user_id', 'kind', 'amount', 'description', 'pay_note', 'status', 'created_at']);
            foreach ($pays as $p) $rows->push(['kind' => 'payment', 'id' => $p->id, 'user_id' => $p->user_id, 'amount' => (float) $p->amount, 'label' => trim($p->description . ' · ' . $p->pay_note), 'pay_kind' => $p->kind, 'status' => $p->status, 'at' => $p->created_at]);
        }

        $rows = $rows->sortByDesc('at')->take(60)->values();
        $names = User::whereIn('id', $rows->pluck('user_id')->unique())->get(['id', 'name', 'nickname', 'email'])->keyBy('id');
        $isSuper = auth()->user()?->role === 'super_admin';   // 회원 이메일은 최고관리자에게만
        $rows = $rows->map(function ($r) use ($names, $isSuper) {
            $u = $names[$r['user_id']] ?? null;
            $r['user_name'] = $u ? ($u->nickname ?: $u->name) : ('#' . $r['user_id']);
            $r['email'] = ($u && $isSuper) ? $u->email : null;
            return $r;
        });

        return response()->json(['success' => true, 'data' => $rows]);
    }

    private function audit(Request $request, string $action, string $target, int $id, array $detail): void
    {
        try {
            if (!Schema::hasTable('admin_audit_log')) return;
            DB::table('admin_audit_log')->insert([
                'admin_id' => auth()->id(), 'action' => $action, 'target_type' => $target, 'target_id' => $id, 'before_value' => null,
                'after_value' => json_encode(['http_status' => 200, 'fields' => array_keys($detail)] + $detail, JSON_UNESCAPED_UNICODE),
                'note' => auth()->user()?->role, 'ip' => $request->ip(), 'created_at' => now(),
            ]);
        } catch (\Throwable $e) { report($e); }
    }
}
