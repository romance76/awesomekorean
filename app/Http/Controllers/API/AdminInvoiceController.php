<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use App\Models\User;
use App\Support\AdminSort;
use App\Support\Invoicing;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 관리자 인보이스 — 목록/상세/미리보기는 관리자(2) 이상, 재발송·무효·자동발송 설정은 최고관리자(3) 전용.
 * 권한 등급은 AdminTier 표가 막고, 여기서도 한 번 더 확인한다(표가 바뀌어도 돈 관련 동작이 열리지 않게).
 * 메일 수신자는 어디서도 바꿀 수 없다 — 발행 시점 회원 이메일(billing_email)로만 나간다.
 */
class AdminInvoiceController extends Controller
{
    private const RESEND_COOLDOWN_SECONDS = 60;

    private function isSuper(): bool
    {
        return optional(auth()->user())->role === 'super_admin';
    }

    private function superOnly()
    {
        return response()->json(['success' => false, 'code' => 'role_required', 'message' => '이 작업은 최고관리자만 할 수 있어요.'], 403);
    }

    private function notReady()
    {
        return response()->json(['success' => false, 'message' => '인보이스 기능이 아직 준비되지 않았어요(서버 업데이트가 끝나지 않았어요).'], 503);
    }

    // ───────────────────────── 목록 ─────────────────────────

    public function index(Request $request)
    {
        if (!Invoicing::ready()) return $this->notReady();

        $q = Invoice::query()->with('latestDelivery');

        $status = (string) $request->input('status', '');
        if (in_array($status, [Invoice::STATUS_ISSUED, Invoice::STATUS_VOID], true)) $q->where('status', $status);

        // 마지막 발송 결과로 걸러내기 (실패한 것만 모아 보기 등)
        $mail = (string) $request->input('mail', '');
        $lastMail = '(select d.status from invoice_deliveries d where d.invoice_id = invoices.id order by d.id desc limit 1)';
        if (in_array($mail, [InvoiceDelivery::SENT, InvoiceDelivery::FAILED, InvoiceDelivery::QUEUED], true)) {
            $q->whereRaw($lastMail . ' = ?', [$mail]);
        } elseif ($mail === 'none') {
            $q->whereRaw($lastMail . ' is null');
        }

        $term = trim((string) $request->input('search', ''));
        if ($term !== '') {
            $like = '%' . addcslashes(mb_substr($term, 0, 100), '%_\\') . '%';
            $q->where(function ($w) use ($like, $term) {
                $w->where('invoice_no', 'like', $like)->orWhere('billing_email', 'like', $like)->orWhere('billing_name', 'like', $like);
                if (ctype_digit($term)) $w->orWhere('payment_id', (int) $term)->orWhere('user_id', (int) $term);
            });
        }

        $this->applyPeriod($q, $request);

        // 결제/오더 목록이 각 주문의 인보이스 칩을 그리기 위해 주문 번호 묶음으로 조회
        $paymentIds = collect(explode(',', (string) $request->input('payment_ids', '')))
            ->map(fn ($v) => (int) trim($v))->filter(fn ($v) => $v > 0)->unique()->take(100)->values();
        if ($paymentIds->isNotEmpty()) $q->whereIn('payment_id', $paymentIds);

        AdminSort::apply($q, $request, [
            'id' => 'id', 'invoice_no' => 'invoice_no', 'status' => 'status', 'total' => 'total_cents',
            'issued_at' => 'issued_at', 'billing_name' => 'billing_name',
        ]);

        $perPage = $paymentIds->isNotEmpty() ? 100 : 30;
        $page = $q->paginate($perPage);
        $page->getCollection()->transform(fn (Invoice $i) => $this->row($i));

        return response()->json([
            'success' => true,
            'data' => $page,
            'summary' => $this->summary(),
            'meta' => ['auto_mail' => Invoicing::autoMailEnabled(), 'can_manage' => $this->isSuper()],
        ]);
    }

    /** 기간: range=today|7d|30d|month|year, 또는 from/to(YYYY-MM-DD, 미국 동부시간 기준) */
    private function applyPeriod($q, Request $request): void
    {
        $tz = Invoicing::TZ;
        $now = Carbon::now($tz);
        $range = (string) $request->input('range', '');
        $from = $to = null;
        if ($range === 'today') { $from = $now->copy()->startOfDay(); }
        elseif ($range === '7d') { $from = $now->copy()->subDays(6)->startOfDay(); }
        elseif ($range === '30d') { $from = $now->copy()->subDays(29)->startOfDay(); }
        elseif ($range === 'month') { $from = $now->copy()->startOfMonth(); }
        elseif ($range === 'year') { $from = $now->copy()->startOfYear(); }
        elseif ($range === 'custom') {
            try {
                if ($request->filled('from')) $from = Carbon::parse((string) $request->input('from'), $tz)->startOfDay();
                if ($request->filled('to')) $to = Carbon::parse((string) $request->input('to'), $tz)->endOfDay();
            } catch (\Throwable $e) {
                $from = $to = null;
            }
        }
        if ($from) $q->where('issued_at', '>=', $from->copy()->utc());
        if ($to) $q->where('issued_at', '<=', $to->copy()->utc());
    }

    private function summary(): array
    {
        $byStatus = Invoice::selectRaw('status, COUNT(*) c, COALESCE(SUM(total_cents),0) s')->groupBy('status')->get()->keyBy('status');
        $failed = (int) DB::table('invoices as i')
            ->whereRaw("(select d.status from invoice_deliveries d where d.invoice_id = i.id order by d.id desc limit 1) = 'failed'")
            ->count();
        return [
            'issued' => (int) ($byStatus['issued']->c ?? 0),
            'issued_cents' => (int) ($byStatus['issued']->s ?? 0),
            'void' => (int) ($byStatus['void']->c ?? 0),
            'mail_failed' => $failed,
        ];
    }

    private function deliveryRow(?InvoiceDelivery $d, array $names = []): ?array
    {
        if (!$d) return null;
        $by = (string) $d->triggered_by;
        return [
            'id' => $d->id,
            'status' => $d->status,
            'to_email' => $d->to_email,
            'error' => $d->error,
            'sent_at' => $d->sent_at,
            'created_at' => $d->created_at,
            'triggered_by' => $by,
            'triggered_label' => $by === 'auto' ? '자동 발송' : ('재발송 · ' . ($names[(int) $by] ?? ('관리자 #' . $by))),
        ];
    }

    private function row(Invoice $i): array
    {
        return [
            'id' => $i->id,
            'invoice_no' => $i->invoice_no,
            'payment_id' => $i->payment_id,
            'user_id' => $i->user_id,
            'status' => $i->status,
            'kind' => $i->kind,
            'currency' => $i->currency,
            'total_cents' => $i->total_cents,
            'billing_name' => $i->billing_name,
            'billing_email' => $i->billing_email,
            'issued_at' => $i->issued_at,
            'voided_at' => $i->voided_at,
            'delivery' => $this->deliveryRow($i->latestDelivery),
        ];
    }

    // ───────────────────────── 상세 ─────────────────────────

    public function show($id)
    {
        if (!Invoicing::ready()) return $this->notReady();

        $inv = Invoice::with([
            'payment:id,kind,status,amount,refunded_amount,points_purchased,created_at',
            'deliveries' => fn ($q) => $q->limit(50),
        ])->findOrFail($id);

        $adminIds = $inv->deliveries->pluck('triggered_by')->filter(fn ($v) => ctype_digit((string) $v))->map(fn ($v) => (int) $v)->unique()->values();
        $names = $adminIds->isEmpty() ? [] : User::whereIn('id', $adminIds)->get()->mapWithKeys(fn ($u) => [$u->id => $u->display_name])->all();
        $nowEmail = $inv->user_id ? User::where('id', $inv->user_id)->value('email') : null;

        $data = $this->row($inv) + [
            'subtotal_cents' => $inv->subtotal_cents,
            'discount_cents' => $inv->discount_cents,
            'line_items' => $inv->line_items,
            'void_reason' => $inv->void_reason,
            'payment' => $inv->payment,
            'member_email_now' => $nowEmail,
            'deliveries' => $inv->deliveries->map(fn ($d) => $this->deliveryRow($d, $names))->values(),
        ];
        return response()->json(['success' => true, 'data' => $data, 'meta' => ['auto_mail' => Invoicing::autoMailEnabled(), 'can_manage' => $this->isSuper()]]);
    }

    /** 관리자 미리보기/인쇄용 — 회원이 받는 것과 같은 HTML */
    public function html($id)
    {
        if (!Invoicing::ready()) return $this->notReady();
        $inv = Invoice::findOrFail($id);
        return response(Invoicing::renderHtml($inv, true, request('lang') === 'en' ? 'en' : 'ko'), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    // ───────────────────────── 재발송 / 무효 / 설정 (최고관리자) ─────────────────────────

    public function resend(Request $request, $id)
    {
        if (!$this->isSuper()) return $this->superOnly();
        if (!Invoicing::ready()) return $this->notReady();

        $inv = Invoice::findOrFail($id);

        // 연타 방지: 같은 인보이스는 1분에 한 번만 (캐시로 동시 클릭 차단 + 발송 이력으로 한 번 더 확인)
        $tooSoon = response()->json(['success' => false, 'message' => '방금 보냈어요. 1분 뒤에 다시 보내 주세요.'], 429);
        if (!Cache::add('invoice-resend:' . $inv->id, 1, self::RESEND_COOLDOWN_SECONDS)) return $tooSoon;
        if (InvoiceDelivery::where('invoice_id', $inv->id)->where('created_at', '>=', now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))->exists()) return $tooSoon;

        // 받는 사람은 요청에서 받지 않는다 — 인보이스에 저장된 회원 이메일(발행 시점)로만 보낸다
        $delivery = Invoicing::queueMail($inv, (string) auth()->id());
        $this->audit('INVOICE resend', $inv, ['delivery_id' => $delivery->id, 'to' => $this->maskEmail($delivery->to_email), 'result' => $delivery->status]);

        if ($delivery->status === InvoiceDelivery::FAILED) {
            Cache::forget('invoice-resend:' . $inv->id);   // 보내지도 못했으니 바로 다시 시도할 수 있게
            return response()->json(['success' => false, 'message' => '메일을 보내지 못했어요: ' . $delivery->error, 'data' => $this->deliveryRow($delivery)], 422);
        }
        return response()->json([
            'success' => true,
            'message' => $this->maskEmail($delivery->to_email) . ' 로 인보이스를 다시 보내도록 요청했어요. 곧 도착해요.',
            'data' => $this->deliveryRow($delivery),
        ]);
    }

    public function void(Request $request, $id)
    {
        if (!$this->isSuper()) return $this->superOnly();
        if (!Invoicing::ready()) return $this->notReady();

        $data = $request->validate(
            ['reason' => ['required', 'string', 'min:5', 'max:200']],
            ['reason.required' => '무효 사유를 적어 주세요.', 'reason.min' => '무효 사유를 5자 이상 적어 주세요.', 'reason.max' => '무효 사유는 200자 이하로 적어 주세요.']
        );

        $inv = Invoice::findOrFail($id);
        if ($inv->status === Invoice::STATUS_VOID) {
            return response()->json(['success' => false, 'message' => '이미 무효 처리된 인보이스예요.'], 422);
        }
        Invoicing::void($inv, trim($data['reason']));
        $this->audit('INVOICE void', $inv, ['reason' => mb_substr(trim($data['reason']), 0, 200)]);

        return response()->json(['success' => true, 'message' => '무효 처리했어요.', 'data' => $this->row($inv->fresh('latestDelivery'))]);
    }

    public function setAutoMail(Request $request)
    {
        if (!$this->isSuper()) return $this->superOnly();
        $data = $request->validate(['enabled' => ['required', 'boolean']], ['enabled.required' => '켜기/끄기 값을 보내 주세요.', 'enabled.boolean' => '켜기/끄기 값만 보낼 수 있어요.']);
        Invoicing::setAutoMail((bool) $data['enabled']);
        $this->audit('INVOICE auto-mail', null, ['enabled' => (bool) $data['enabled']]);
        return response()->json(['success' => true, 'message' => $data['enabled'] ? '결제가 끝나면 인보이스를 자동으로 보내요.' : '자동 발송을 껐어요. (인보이스는 계속 만들어지고, 필요하면 다시 보내기로 보낼 수 있어요)', 'data' => ['auto_mail' => (bool) $data['enabled']]]);
    }

    // ───────────────────────── 보조 ─────────────────────────

    private function maskEmail(?string $email): string
    {
        $email = (string) $email;
        if (!str_contains($email, '@')) return $email === '' ? '(주소 없음)' : '***';
        [$local, $domain] = explode('@', $email, 2);
        return mb_substr($local, 0, 2) . '***@' . $domain;
    }

    /** 누가 무엇을 했는지 감사 로그에 한 줄 더(요청 자체는 AdminTier 가 이미 기록). 이메일은 가려서 남긴다. */
    private function audit(string $action, ?Invoice $inv, array $detail): void
    {
        try {
            if (!Schema::hasTable('admin_audit_log')) return;
            DB::table('admin_audit_log')->insert([
                'admin_id' => auth()->id(),
                'action' => mb_substr($action, 0, 100),
                'target_type' => 'invoices',
                'target_id' => $inv?->id,
                'before_value' => null,
                'after_value' => json_encode($detail + ($inv ? ['invoice_no' => $inv->invoice_no] : []), JSON_UNESCAPED_UNICODE),
                'note' => optional(auth()->user())->role,
                'ip' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
