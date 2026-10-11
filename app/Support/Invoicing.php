<?php

namespace App\Support;

use App\Jobs\SendInvoiceEmail;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use App\Models\Payment;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 인보이스 발행·무효·메일 큐잉 — 한 곳에서만 한다.
 *
 * "결제가 끝났다"는 신호는 결제 업체(Stripe → 곧 CardPointe)가 아니라 payments 행의 status 변화다:
 *   completed(포인트 구매) / captured(달러 직접 결제) 가 되면 발행, refunded(전액 환불)가 되면 무효.
 * Payment 모델의 created/updated 훅 한 줄이 onPaymentChanged() 를 부르므로, 결제 코드는 거의 건드리지 않는다.
 *
 * 인보이스 때문에 결제가 깨지면 안 되므로 훅 안의 모든 예외는 삼키고 로그만 남긴다.
 */
class Invoicing
{
    public const PAID = ['completed', 'captured'];
    public const PREFIX = 'AK';
    public const TZ = 'America/New_York';
    public const AUTO_MAIL_KEY = 'invoice_auto_mail';

    private static bool $ready = false;

    /** 마이그레이션 전에 코드가 먼저 나가도 결제가 깨지지 않게 표 존재를 확인 (있다고 확인되면 그 프로세스에선 다시 묻지 않음) */
    public static function ready(): bool
    {
        if (self::$ready) return true;
        try {
            self::$ready = Schema::hasTable('invoices') && Schema::hasTable('invoice_deliveries');
        } catch (\Throwable $e) {
            self::$ready = false;
        }
        return self::$ready;
    }

    /** Payment 모델 훅에서 호출. 어떤 예외도 밖으로 내보내지 않는다. */
    public static function onPaymentChanged(Payment $payment, bool $created): void
    {
        try {
            if (!self::ready()) return;
            $changed = $created || $payment->wasChanged('status');
            if (!$changed) return;

            $status = (string) $payment->status;
            if (in_array($status, self::PAID, true)) {
                $invoice = self::issueForPayment($payment);
                if ($invoice->wasRecentlyCreated && self::autoMailEnabled()) {
                    self::queueMail($invoice, 'auto');
                }
            } elseif ($status === 'refunded') {
                self::voidForPayment($payment, '전액 환불 (주문 #' . $payment->id . ')');
            }
        } catch (\Throwable $e) {
            Log::warning('[인보이스] 자동 처리 실패(결제 흐름은 그대로 진행) 결제 #' . ($payment->id ?? '?') . ': ' . self::oneLine($e->getMessage()));
        }
    }

    // ───────────────────────── 발행 ─────────────────────────

    private static ?bool $billingCol = null;
    private static function hasBillingDetails(): bool
    {
        return self::$billingCol ??= \Illuminate\Support\Facades\Schema::hasColumn('invoices', 'billing_details');
    }

    /** 결제 한 건의 인보이스를 발행한다. 이미 있으면 그걸 돌려준다(중복 발행 없음). */
    public static function issueForPayment(Payment $payment, ?Carbon $issuedAt = null): Invoice
    {
        $existing = Invoice::where('payment_id', $payment->id)->first();
        if ($existing) return $existing;

        $user = User::find($payment->user_id);
        $cents = (int) round(((float) $payment->amount) * 100);
        $issuedAt = $issuedAt ?: now();
        $data = [
            'payment_id' => $payment->id,
            'user_id' => $payment->user_id,
            'status' => Invoice::STATUS_ISSUED,
            'kind' => $payment->kind ?: Payment::KIND_POINTS,
            'currency' => strtoupper((string) ($payment->currency ?: 'USD')),
            'subtotal_cents' => $cents,
            'discount_cents' => 0,
            'total_cents' => $cents,
            'line_items' => self::lineItemsFor($payment, $cents),
            'billing_name' => $user ? mb_substr((string) ($user->getAttributes()['name'] ?? '') ?: $user->display_name, 0, 100) : null,
            'billing_email' => $user ? mb_substr((string) $user->email, 0, 190) : null,
            'issued_at' => $issuedAt,
        ];
        // 정식 인보이스용: 발행 시점의 받는 사람 정보(전화·주소)와 결제 수단(카드 종류·끝 4자리)을 스냅샷으로 남긴다
        if (self::hasBillingDetails()) {
            $a = $user ? $user->getAttributes() : [];
            $data['billing_details'] = array_filter([
                'real_name' => isset($a['real_name']) ? mb_substr((string) $a['real_name'], 0, 100) : null,
                'phone' => isset($a['phone']) ? mb_substr((string) $a['phone'], 0, 30) : null,
                'address' => isset($a['address']) ? mb_substr((string) $a['address'], 0, 200) : null,
                'city' => isset($a['city']) ? mb_substr((string) $a['city'], 0, 100) : null,
                'state' => isset($a['state']) ? mb_substr((string) $a['state'], 0, 20) : null,
                'zipcode' => isset($a['zipcode']) ? mb_substr((string) $a['zipcode'], 0, 10) : null,
                'card_brand' => $payment->card_brand ?? null,
                'card_last4' => $payment->card_last4 ?? null,
                'method' => in_array($payment->kind, Payment::DIRECT_KINDS, true) || $payment->stripe_payment_id ? 'card' : null,
            ], fn ($v) => $v !== null && $v !== '');
        }

        // 번호가 겹치면(동시에 두 건이 같은 번호를 잡음) 새 번호로 다시 시도. 같은 결제가 먼저 발행됐다면 그걸 돌려준다.
        for ($try = 0; $try < 8; $try++) {
            try {
                return DB::transaction(fn () => Invoice::create($data + ['invoice_no' => self::nextNumber($issuedAt)]));
            } catch (QueryException $e) {
                if (!self::isRetryable($e)) throw $e;
                $dupe = Invoice::where('payment_id', $payment->id)->first();
                if ($dupe) return $dupe;
                usleep(random_int(5, 40) * 1000);
            }
        }
        throw new \RuntimeException('인보이스 번호를 만들지 못했어요(번호 충돌이 반복됨).');
    }

    /** AK-2026-000123 — 연도(애틀랜타 기준)별 일련번호. 반드시 트랜잭션 안에서 부른다(행 잠금 읽기). */
    public static function nextNumber(Carbon $at): string
    {
        $prefix = self::PREFIX . '-' . $at->copy()->setTimezone(self::TZ)->format('Y') . '-';
        $last = Invoice::where('invoice_no', 'like', $prefix . '%')
            ->orderByRaw('LENGTH(invoice_no) DESC')
            ->orderByDesc('invoice_no')
            ->lockForUpdate()
            ->value('invoice_no');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    private static function isRetryable(QueryException $e): bool
    {
        $state = (string) ($e->errorInfo[0] ?? $e->getCode());
        $driver = (int) ($e->errorInfo[1] ?? 0);
        return $state === '23000' || $state === '40001' || in_array($driver, [1062, 1213, 19], true);
    }

    /** 영수증에 찍을 상품 줄 — 결제 종류별로 쉬운 이름. [{name,name_en,qty,unit_cents,amount_cents}] */
    public static function lineItemsFor(Payment $payment, int $cents): array
    {
        $kind = $payment->kind ?: Payment::KIND_POINTS;
        $desc = trim((string) $payment->description);
        if ($kind === Payment::KIND_POINTS) {
            $pts = (int) $payment->points_purchased;
            $name = $pts > 0 ? '포인트 충전 ' . number_format($pts) . 'P' : '포인트 충전';
            $en = $pts > 0 ? 'Point top-up (' . number_format($pts) . ' P)' : 'Point top-up';
        } elseif ($kind === Payment::KIND_FLYER) {
            $name = $desc !== '' ? $desc : 'NEW 전면광고(전단) 광고';
            $en = 'Flyer advertising';
        } elseif ($kind === Payment::KIND_EVENT_REQUEST) {
            $name = $desc !== '' ? $desc : '경품 이벤트 의뢰';
            $en = 'Sweepstakes event request';
        } elseif ($kind === Payment::KIND_BANNER) {
            $name = $desc !== '' ? $desc : '배너 광고';
            $en = 'Banner advertising';
        } elseif ($kind === Payment::KIND_TEXT_AD) {
            $name = $desc !== '' ? $desc : '텍스트 광고';
            $en = 'Text advertising';
        } elseif ($kind === Payment::KIND_PROMOTION) {
            $name = $desc !== '' ? $desc : '상위노출';
            $en = 'Featured listing (promotion)';
        } else {
            $name = $desc !== '' ? $desc : '결제';
            $en = 'Payment';
        }
        return [[
            'name' => mb_substr($name, 0, 200),
            'name_en' => $en,
            'qty' => 1,
            'unit_cents' => $cents,
            'amount_cents' => $cents,
        ]];
    }

    // ───────────────────────── 무효 ─────────────────────────

    /** 전액 환불 등으로 결제가 무효가 되면 인보이스를 void 로 표시 (이미 void 면 그대로) */
    public static function voidForPayment(Payment $payment, string $reason): ?Invoice
    {
        $invoice = Invoice::where('payment_id', $payment->id)->first();
        return $invoice ? self::void($invoice, $reason) : null;
    }

    public static function void(Invoice $invoice, string $reason): Invoice
    {
        if ($invoice->status === Invoice::STATUS_VOID) return $invoice;
        $invoice->update([
            'status' => Invoice::STATUS_VOID,
            'voided_at' => now(),
            'void_reason' => mb_substr(trim($reason), 0, 255),
        ]);
        return $invoice;
    }

    // ───────────────────────── 메일 ─────────────────────────

    /** 사이트 설정 invoice_auto_mail — 비어 있으면 켜짐(기본), 0/false/off/no 면 꺼짐 */
    public static function autoMailEnabled(): bool
    {
        try {
            $v = SiteSetting::where('key', self::AUTO_MAIL_KEY)->value('value');
        } catch (\Throwable $e) {
            return true;
        }
        if ($v === null || trim((string) $v) === '') return true;
        return !in_array(strtolower(trim((string) $v)), ['0', 'false', 'off', 'no'], true);
    }

    public static function setAutoMail(bool $on): void
    {
        SiteSetting::updateOrCreate(['key' => self::AUTO_MAIL_KEY], ['value' => $on ? '1' : '0', 'group' => 'invoice']);
    }

    /** 보낼 수 없는 주소면 이유(한 줄), 괜찮으면 null */
    public static function addressProblem(?string $email): ?string
    {
        $email = trim((string) $email);
        if ($email === '') return '회원 이메일이 없어요';
        if (mb_strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) return '이메일 형식이 올바르지 않아요';
        $domain = strtolower(substr(strrchr($email, '@'), 1));
        if (preg_match('/\.(local|invalid|localhost|example)$/', $domain) || in_array($domain, ['example.com', 'example.net', 'example.org'], true)) {
            return '실제로 쓰는 주소가 아니에요(내부용·예시 주소)';
        }
        return null;
    }

    /**
     * 발송 이력 한 줄을 만들고 메일을 큐에 넣는다. 주소가 잘못이면 큐에 넣지 않고 failed 로만 기록.
     * 트랜잭션 안에서 불려도 커밋된 뒤에 큐에 넣는다(롤백되면 메일도 안 나감).
     */
    public static function queueMail(Invoice $invoice, string $triggeredBy = 'auto'): InvoiceDelivery
    {
        $to = trim((string) $invoice->billing_email);
        $delivery = InvoiceDelivery::create([
            'invoice_id' => $invoice->id,
            'to_email' => $to !== '' ? mb_substr($to, 0, 190) : null,
            'status' => InvoiceDelivery::QUEUED,
            'triggered_by' => mb_substr($triggeredBy, 0, 20),
        ]);

        $problem = self::addressProblem($to);
        if ($problem) {
            $delivery->update(['status' => InvoiceDelivery::FAILED, 'error' => $problem]);
            return $delivery;
        }

        DB::afterCommit(function () use ($delivery) {
            try {
                SendInvoiceEmail::dispatch($delivery->id);
            } catch (\Throwable $e) {
                $delivery->update(['status' => InvoiceDelivery::FAILED, 'error' => self::oneLine('메일 대기열에 넣지 못했어요: ' . $e->getMessage())]);
            }
        });
        return $delivery;
    }

    /** 예외 메시지를 한 줄·짧게 + 키처럼 보이는 값은 가린다 */
    public static function oneLine(string $msg, int $max = 200): string
    {
        $msg = preg_replace('/\s+/u', ' ', $msg) ?? $msg;
        $msg = preg_replace('/\b(re_[A-Za-z0-9_\-]{8,}|sk_[A-Za-z0-9_\-]{8,}|Bearer\s+\S+)/', '***', $msg) ?? $msg;
        return mb_substr(trim($msg), 0, $max);
    }

    // ───────────────────────── 화면(HTML)·목록 보조 ─────────────────────────

    /** 회사 정보 — 관리자 "회사 정보" 설정(site_settings)을 그대로 쓴다. 비어 있는 칸은 빈 문자열. */
    public static function companyInfo(): array
    {
        try {
            $s = SiteSetting::whereIn('key', [
                'company_name', 'site_name', 'ceo_name', 'business_number', 'address', 'company_address',
                'phone', 'contact_phone', 'email', 'contact_email', 'logo_url',
            ])->pluck('value', 'key');
        } catch (\Throwable $e) {
            $s = collect();
        }
        $pick = function (string ...$keys) use ($s) {
            foreach ($keys as $k) {
                $v = trim((string) ($s[$k] ?? ''));
                if ($v !== '') return $v;
            }
            return '';
        };
        return [
            'name' => $pick('company_name', 'site_name') ?: 'AwesomeKorean',
            'site' => $pick('site_name') ?: 'AwesomeKorean',
            'ceo' => $pick('ceo_name'),
            'business_number' => $pick('business_number'),
            'address' => $pick('address', 'company_address'),
            'phone' => $pick('phone', 'contact_phone'),
            'email' => $pick('email', 'contact_email'),
            'logo' => self::absoluteUrl($pick('logo_url') ?: '/images/logo.png'),
            'url' => rtrim((string) config('app.url'), '/'),
        ];
    }

    public static function absoluteUrl(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) return $path;
        return rtrim((string) config('app.url'), '/') . '/' . ltrim($path, '/');
    }

    public static function money(int $cents, string $currency = 'USD'): string
    {
        return ($currency === 'USD' ? '$' : '') . number_format($cents / 100, 2) . ($currency === 'USD' ? '' : ' ' . $currency);
    }

    /** 메일·웹 보기·텍스트 메일이 같이 쓰는 화면 데이터 */
    public static function viewData(Invoice $invoice, bool $forWeb = false): array
    {
        $issued = $invoice->issued_at ?: $invoice->created_at;
        $kindLabels = [
            Payment::KIND_POINTS => ['포인트 구매', 'Point purchase'],
            Payment::KIND_FLYER => ['NEW 전면광고', 'Flyer advertising'],
            Payment::KIND_EVENT_REQUEST => ['경품 이벤트 의뢰', 'Sweepstakes event request'],
            Payment::KIND_BANNER => ['배너 광고', 'Banner advertising'],
            Payment::KIND_TEXT_AD => ['텍스트 광고', 'Text advertising'],
            Payment::KIND_PROMOTION => ['상위노출', 'Featured listing'],
        ];
        return [
            'inv' => $invoice,
            'company' => self::companyInfo(),
            'items' => (array) ($invoice->line_items ?? []),
            'issuedText' => $issued ? $issued->copy()->setTimezone(self::TZ)->format('Y-m-d H:i') . ' (ET)' : '',
            'voidedText' => $invoice->voided_at ? $invoice->voided_at->copy()->setTimezone(self::TZ)->format('Y-m-d H:i') . ' (ET)' : '',
            'kindLabel' => $kindLabels[$invoice->kind ?: Payment::KIND_POINTS] ?? ['결제', 'Payment'],
            'cur' => $invoice->currency ?: 'USD',
            'forWeb' => $forWeb,
            'bill' => self::billTo($invoice),
            'payInfo' => self::paymentInfo($invoice),
        ];
    }

    /** 받는 분 정보 — 발행 시점 스냅샷(billing_details)을 우선, 없으면(옛 인보이스) 지금 회원 정보로 채운다 */
    public static function billTo(Invoice $invoice): array
    {
        $d = is_array($invoice->billing_details ?? null) ? $invoice->billing_details : [];
        if (!$d && $invoice->user_id) {
            $u = User::find($invoice->user_id);
            $a = $u ? $u->getAttributes() : [];
            $d = array_filter(['real_name' => $a['real_name'] ?? null, 'phone' => $a['phone'] ?? null, 'address' => $a['address'] ?? null,
                'city' => $a['city'] ?? null, 'state' => $a['state'] ?? null, 'zipcode' => $a['zipcode'] ?? null]);
        }
        $cityLine = trim(implode(', ', array_filter([$d['city'] ?? null, trim(($d['state'] ?? '') . ' ' . ($d['zipcode'] ?? ''))])), ', ');
        return [
            'name' => ($d['real_name'] ?? '') ?: ($invoice->billing_name ?: '-'),
            'nickname' => (($d['real_name'] ?? '') && $invoice->billing_name && $invoice->billing_name !== ($d['real_name'] ?? '')) ? $invoice->billing_name : '',
            'email' => (string) $invoice->billing_email,
            'phone' => (string) ($d['phone'] ?? ''),
            'address' => (string) ($d['address'] ?? ''),
            'city_line' => $cityLine,
            'member_no' => $invoice->user_id ? 'Member #' . $invoice->user_id : '',
        ];
    }

    /** 결제 수단·결제 시각·결제 참조 번호(일부만) */
    public static function paymentInfo(Invoice $invoice): array
    {
        $d = is_array($invoice->billing_details ?? null) ? $invoice->billing_details : [];
        $p = $invoice->payment_id ? Payment::find($invoice->payment_id) : null;
        $brand = $d['card_brand'] ?? ($p->card_brand ?? null);
        $last4 = $d['card_last4'] ?? ($p->card_last4 ?? null);
        $method = $last4 ? (strtoupper((string) ($brand ?: 'CARD')) . ' •••• ' . $last4) : 'Credit / Debit card';
        $paidAt = $p ? ($p->captured_at ?: $p->updated_at) : null;
        $ref = $p && $p->stripe_payment_id ? '…' . substr((string) $p->stripe_payment_id, -8) : '';
        return [
            'method' => $method,
            'paid_at' => $paidAt ? $paidAt->copy()->setTimezone(self::TZ)->format('M j, Y g:i A') . ' ET' : '',
            'ref' => $ref,
            'order_no' => $p ? '#' . $p->id : '',
        ];
    }

    /** 메일은 기존 간단한 양식, 웹 보기·인쇄는 레터 사이즈(8.5×11in) 정식 인보이스 */
    public static function renderHtml(Invoice $invoice, bool $forWeb = false): string
    {
        return view($forWeb ? 'invoices.letter' : 'emails.invoice', self::viewData($invoice, $forWeb))->render();
    }

    /** 회원 결제 내역 목록에 invoice_no 를 붙인다 (표가 아직 없으면 조용히 건너뜀) */
    public static function attachInvoiceNos($payments): void
    {
        try {
            if (!self::ready() || $payments->isEmpty()) return;
            $nos = Invoice::whereIn('payment_id', $payments->pluck('id'))->pluck('invoice_no', 'payment_id');
            foreach ($payments as $p) $p->setAttribute('invoice_no', $nos[$p->id] ?? null);
        } catch (\Throwable $e) {
            // 영수증 번호가 안 붙을 뿐 결제 내역은 그대로 보여 준다
        }
    }
}
