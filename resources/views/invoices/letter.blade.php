<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="robots" content="noindex, nofollow">
<title>Invoice {{ $inv->invoice_no }} · {{ $company['name'] }}</title>
{{-- 정식 인보이스 (US Letter 8.5 × 11 in). 화면에서는 종이처럼, 인쇄/PDF 저장 시 한 장에 맞게. 메일은 emails/invoice.blade.php (간단한 양식) --}}
<style>
  @page { size: letter; margin: 0; }
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body { background: #e5e7eb; color: #111827; font-family: 'Helvetica Neue', Helvetica, Arial, 'Apple SD Gothic Neo', 'Malgun Gothic', 'Noto Sans KR', sans-serif; font-size: 11pt; line-height: 1.45; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .toolbar { max-width: 8.5in; margin: 16px auto 0; padding: 0 12px; display: flex; gap: 8px; justify-content: flex-end; }
  .toolbar button { min-height: 40px; padding: 0 18px; border: 0; border-radius: 8px; background: #111827; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; }
  .toolbar .hint { margin-right: auto; align-self: center; font-size: 12px; color: #4b5563; }
  .sheet { width: 8.5in; min-height: 11in; margin: 12px auto 32px; background: #fff; padding: 0.6in 0.65in 0.55in; box-shadow: 0 6px 28px rgba(0,0,0,.12); position: relative; display: flex; flex-direction: column; }
  .top { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; padding-bottom: 18px; border-bottom: 3px solid #111827; }
  .brand img { height: 46px; max-width: 220px; object-fit: contain; display: block; }
  .brand .co { margin-top: 10px; font-size: 9.5pt; color: #374151; line-height: 1.55; }
  .brand .co b { font-size: 11pt; color: #111827; }
  .title { text-align: right; }
  .title h1 { margin: 0; font-size: 28pt; letter-spacing: 3px; font-weight: 800; color: #111827; }
  .title .kr { font-size: 10pt; color: #6b7280; letter-spacing: 1px; }
  .meta { margin-top: 12px; border-collapse: collapse; margin-left: auto; font-size: 9.5pt; }
  .meta td { padding: 2px 0 2px 14px; text-align: right; }
  .meta td.k { color: #6b7280; }
  .meta td.v { font-weight: 700; color: #111827; }
  .stamp { position: absolute; top: 2.15in; right: 0.65in; transform: rotate(-12deg); border: 3px solid #059669; color: #059669; font-weight: 800; font-size: 20pt; letter-spacing: 4px; padding: 2px 14px; border-radius: 6px; opacity: .85; }
  .stamp.void { border-color: #dc2626; color: #dc2626; }
  .parties { display: flex; gap: 28px; margin-top: 22px; }
  .party { flex: 1; }
  .label { font-size: 8.5pt; font-weight: 700; letter-spacing: 1.2px; color: #6b7280; text-transform: uppercase; margin-bottom: 4px; }
  .party .name { font-size: 12pt; font-weight: 700; }
  .party div { font-size: 10pt; color: #374151; }
  table.items { width: 100%; border-collapse: collapse; margin-top: 26px; font-size: 10pt; }
  table.items th { background: #111827; color: #fff; font-size: 8.5pt; letter-spacing: 1px; text-transform: uppercase; font-weight: 700; padding: 8px 10px; text-align: left; }
  table.items th.r, table.items td.r { text-align: right; }
  table.items th.c, table.items td.c { text-align: center; }
  table.items td { padding: 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
  table.items td .en { font-size: 8.5pt; color: #6b7280; }
  .totals { margin-top: 10px; margin-left: auto; width: 3.2in; border-collapse: collapse; font-size: 10pt; }
  .totals td { padding: 5px 10px; }
  .totals td.r { text-align: right; white-space: nowrap; }
  .totals tr.total td { border-top: 2px solid #111827; font-size: 12.5pt; font-weight: 800; padding-top: 8px; }
  .totals tr.due td { background: #f3f4f6; font-weight: 700; }
  .paybox { margin-top: 24px; display: flex; gap: 28px; font-size: 9.5pt; }
  .paybox > div { flex: 1; }
  .paybox table { border-collapse: collapse; }
  .paybox td { padding: 2px 12px 2px 0; vertical-align: top; }
  .paybox td.k { color: #6b7280; white-space: nowrap; }
  .void-note { margin-top: 18px; padding: 10px 12px; border: 1px solid #fecaca; background: #fef2f2; color: #991b1b; font-size: 9.5pt; border-radius: 6px; }
  .foot { margin-top: auto; padding-top: 16px; border-top: 1px solid #e5e7eb; font-size: 8.5pt; color: #6b7280; display: flex; justify-content: space-between; gap: 16px; }
  @media print {
    body { background: #fff; }
    .toolbar { display: none !important; }
    .sheet { margin: 0; box-shadow: none; width: 8.5in; min-height: 11in; }
  }
  @media screen and (max-width: 860px) {
    .sheet { width: auto; min-height: 0; margin: 10px; padding: 22px 18px; }
    .top, .parties, .paybox { flex-direction: column; gap: 14px; }
    .title { text-align: left; } .meta { margin-left: 0; } .meta td { text-align: left; padding: 2px 14px 2px 0; }
    .stamp { position: static; display: inline-block; margin-top: 10px; transform: none; font-size: 14pt; }
    .totals { width: 100%; }
    .toolbar .hint { display: none; }
  }
</style>
</head>
<body>

@if($forWeb)
<div class="toolbar">
  <span class="hint">인쇄 창에서 "PDF로 저장"을 고르면 PDF 파일로 받을 수 있어요. · Choose "Save as PDF" in the print dialog.</span>
  <button type="button" onclick="window.print()">인쇄 / PDF 저장</button>
</div>
@endif

<div class="sheet">
  <div class="top">
    <div class="brand">
      <img src="{{ $company['logo'] }}" alt="{{ $company['site'] }}">
      <div class="co">
        <b>{{ $company['name'] }}</b><br>
        @if($company['address']){{ $company['address'] }}<br>@endif
        @if($company['phone'])Tel {{ $company['phone'] }}<br>@endif
        @if($company['email']){{ $company['email'] }}<br>@endif
        {{ preg_replace('#^https?://#', '', $company['url']) }}
        @if($company['business_number'])<br>Business No. {{ $company['business_number'] }}@endif
      </div>
    </div>
    <div class="title">
      <h1>INVOICE</h1>
      <div class="kr">인보이스 · {{ $kindLabel[1] }}</div>
      <table class="meta">
        <tr><td class="k">Invoice No. 인보이스 번호</td><td class="v">{{ $inv->invoice_no }}</td></tr>
        <tr><td class="k">Issue date 발행일</td><td class="v">{{ $issuedText }}</td></tr>
        @if($payInfo['order_no'])<tr><td class="k">Order 주문 번호</td><td class="v">{{ $payInfo['order_no'] }}</td></tr>@endif
        <tr><td class="k">Currency 통화</td><td class="v">{{ $cur }}</td></tr>
      </table>
    </div>
  </div>

  @if($inv->status === 'void')
    <div class="stamp void">VOID</div>
  @else
    <div class="stamp">PAID</div>
  @endif

  <div class="parties">
    <div class="party">
      <div class="label">Bill To · 받는 분</div>
      <div class="name">{{ $bill['name'] }}</div>
      @if($bill['nickname'])<div>({{ $bill['nickname'] }})</div>@endif
      @if($bill['address'])<div>{{ $bill['address'] }}</div>@endif
      @if($bill['city_line'])<div>{{ $bill['city_line'] }}</div>@endif
      @if($bill['email'])<div>{{ $bill['email'] }}</div>@endif
      @if($bill['phone'])<div>{{ $bill['phone'] }}</div>@endif
      @if($bill['member_no'])<div style="color:#6b7280;font-size:9pt;">{{ $bill['member_no'] }}</div>@endif
    </div>
    <div class="party">
      <div class="label">From · 발행</div>
      <div class="name">{{ $company['name'] }}</div>
      @if($company['ceo'])<div>CEO {{ $company['ceo'] }}</div>@endif
      @if($company['address'])<div>{{ $company['address'] }}</div>@endif
      @if($company['email'])<div>{{ $company['email'] }}</div>@endif
    </div>
  </div>

  <table class="items">
    <thead>
      <tr><th style="width:34px;">#</th><th>Description · 내용</th><th class="c" style="width:52px;">Qty</th><th class="r" style="width:100px;">Unit price</th><th class="r" style="width:100px;">Amount</th></tr>
    </thead>
    <tbody>
      @foreach($items as $i => $it)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $it['name'] ?? '' }}@if(!empty($it['name_en']))<div class="en">{{ $it['name_en'] }}</div>@endif</td>
        <td class="c">{{ (int) ($it['qty'] ?? 1) }}</td>
        <td class="r">{{ \App\Support\Invoicing::money((int) ($it['unit_cents'] ?? $it['amount_cents'] ?? 0), $cur) }}</td>
        <td class="r">{{ \App\Support\Invoicing::money((int) ($it['amount_cents'] ?? 0), $cur) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <table class="totals">
    <tr><td>Subtotal 소계</td><td class="r">{{ \App\Support\Invoicing::money((int) $inv->subtotal_cents, $cur) }}</td></tr>
    @if($inv->discount_cents > 0)
    <tr><td>Discount 할인</td><td class="r" style="color:#dc2626;">-{{ \App\Support\Invoicing::money((int) $inv->discount_cents, $cur) }}</td></tr>
    @endif
    <tr><td>Sales tax 판매세</td><td class="r">{{ \App\Support\Invoicing::money(0, $cur) }}</td></tr>
    <tr class="total"><td>Total 합계</td><td class="r">{{ \App\Support\Invoicing::money((int) $inv->total_cents, $cur) }}</td></tr>
    @if($inv->status !== 'void')
    <tr><td>Amount paid 결제 금액</td><td class="r">{{ \App\Support\Invoicing::money((int) $inv->total_cents, $cur) }}</td></tr>
    <tr class="due"><td>Balance due 남은 금액</td><td class="r">{{ \App\Support\Invoicing::money(0, $cur) }}</td></tr>
    @endif
  </table>

  <div class="paybox">
    <div>
      <div class="label">Payment · 결제 정보</div>
      <table>
        <tr><td class="k">Method 결제 수단</td><td>{{ $payInfo['method'] }}</td></tr>
        @if($payInfo['paid_at'])<tr><td class="k">Paid on 결제일</td><td>{{ $payInfo['paid_at'] }}</td></tr>@endif
        @if($payInfo['ref'])<tr><td class="k">Reference 결제 참조</td><td>{{ $payInfo['ref'] }}</td></tr>@endif
        <tr><td class="k">Status 상태</td><td>{{ $inv->status === 'void' ? 'Void 무효' : 'Paid in full 결제 완료' }}</td></tr>
      </table>
    </div>
    <div>
      <div class="label">Notes · 안내</div>
      <div style="color:#374151;">
        Thank you for your business with {{ $company['site'] }}. 이용해 주셔서 감사합니다.<br>
        문의: {{ $company['email'] ?: $company['url'] }}
        @if(in_array($inv->kind, ['promotion'], true))<br>상위노출 카드 결제는 환불되지 않습니다. Featured listing card payments are non-refundable.@endif
      </div>
    </div>
  </div>

  @if($inv->status === 'void')
  <div class="void-note">
    <b>This invoice is void · 무효 처리된 인보이스</b>
    @if($inv->void_reason) — {{ $inv->void_reason }}@endif
    @if($voidedText) ({{ $voidedText }})@endif
  </div>
  @endif

  <div class="foot">
    <div>{{ $company['name'] }}@if($company['address']) · {{ $company['address'] }}@endif</div>
    <div>{{ $inv->invoice_no }} · Page 1 of 1</div>
  </div>
</div>

</body>
</html>
