<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>인보이스 {{ $inv->invoice_no }} / Invoice</title>
<style>
@media print { .no-print { display: none !important; } body { background: #ffffff !important; } }
@media only screen and (max-width: 480px) { .wrap { padding: 12px !important; } .stack { display: block !important; width: 100% !important; text-align: left !important; } }
</style>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','Apple SD Gothic Neo','Malgun Gothic','Noto Sans KR',Arial,sans-serif;color:#1f2937;-webkit-text-size-adjust:100%;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6;">
<tr><td align="center" class="wrap" style="padding:24px 12px;">

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">

    {{-- 머리글 --}}
    <tr><td style="background:#f59e0b;padding:20px 24px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
        <td class="stack" style="vertical-align:middle;">
          <img src="{{ $company['logo'] }}" alt="{{ $company['site'] }}" height="32" style="display:block;height:32px;max-width:180px;border:0;background:#ffffff;border-radius:6px;padding:3px 8px;">
        </td>
        <td class="stack" align="right" style="vertical-align:middle;color:#ffffff;">
          <div style="font-size:20px;font-weight:800;line-height:1.2;">인보이스</div>
          <div style="font-size:12px;opacity:.9;letter-spacing:.5px;">INVOICE</div>
        </td>
      </tr></table>
    </td></tr>

    @if($inv->status === 'void')
    <tr><td style="background:#fef2f2;border-bottom:1px solid #fecaca;padding:12px 24px;color:#b91c1c;font-size:14px;line-height:1.5;">
      <b>무효 처리된 인보이스예요 / VOID</b><br>
      환불 등으로 효력이 없어졌어요. This invoice is no longer valid.
      @if($inv->void_reason)<br><span style="font-size:13px;">사유 / Reason: {{ $inv->void_reason }}</span>@endif
      @if($voidedText)<br><span style="font-size:12px;color:#991b1b;">{{ $voidedText }}</span>@endif
    </td></tr>
    @endif

    {{-- 번호·날짜 --}}
    <tr><td style="padding:22px 24px 8px 24px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;line-height:1.55;">
        <tr>
          <td class="stack" style="vertical-align:top;padding-bottom:10px;">
            <div style="font-size:12px;color:#6b7280;">인보이스 번호 / Invoice No.</div>
            <div style="font-size:18px;font-weight:800;color:#111827;word-break:break-all;">{{ $inv->invoice_no }}</div>
          </td>
          <td class="stack" align="right" style="vertical-align:top;padding-bottom:10px;">
            <div style="font-size:12px;color:#6b7280;">발행일 / Issued</div>
            <div style="font-size:14px;color:#111827;">{{ $issuedText }}</div>
          </td>
        </tr>
        <tr>
          <td class="stack" style="vertical-align:top;padding-bottom:6px;">
            <div style="font-size:12px;color:#6b7280;">받는 분 / Billed to</div>
            <div style="font-size:15px;font-weight:700;color:#111827;">{{ $inv->billing_name ?: '-' }}</div>
            <div style="font-size:13px;color:#4b5563;word-break:break-all;">{{ $inv->billing_email }}</div>
            @if(!empty($bill['phone']))<div style="font-size:13px;color:#4b5563;">{{ $bill['phone'] }}</div>@endif
            @if(!empty($bill['address']) || !empty($bill['city_line']))<div style="font-size:13px;color:#4b5563;">{{ trim(($bill['address'] ?? '') . ' ' . ($bill['city_line'] ?? '')) }}</div>@endif
          </td>
          <td class="stack" align="right" style="vertical-align:top;padding-bottom:6px;">
            <div style="font-size:12px;color:#6b7280;">구분 / Type</div>
            <div style="font-size:14px;color:#111827;">{{ $kindLabel[0] }} <span style="color:#6b7280;">/ {{ $kindLabel[1] }}</span></div>
          </td>
        </tr>
      </table>
    </td></tr>

    {{-- 상품 --}}
    <tr><td style="padding:12px 24px 4px 24px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;border-collapse:collapse;">
        <tr style="background:#f9fafb;">
          <th align="left" style="padding:9px 10px;font-size:12px;color:#6b7280;font-weight:600;border-top:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;">상품 / Description</th>
          <th align="center" style="padding:9px 6px;font-size:12px;color:#6b7280;font-weight:600;border-top:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;width:44px;">수량<br>Qty</th>
          <th align="right" style="padding:9px 10px;font-size:12px;color:#6b7280;font-weight:600;border-top:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;width:96px;">금액<br>Amount</th>
        </tr>
        @foreach($items as $it)
        <tr>
          <td style="padding:11px 10px;border-bottom:1px solid #f3f4f6;vertical-align:top;">
            <div style="color:#111827;">{{ $it['name'] ?? '' }}</div>
            @if(!empty($it['name_en']))<div style="font-size:12px;color:#6b7280;">{{ $it['name_en'] }}</div>@endif
          </td>
          <td align="center" style="padding:11px 6px;border-bottom:1px solid #f3f4f6;vertical-align:top;color:#374151;">{{ (int) ($it['qty'] ?? 1) }}</td>
          <td align="right" style="padding:11px 10px;border-bottom:1px solid #f3f4f6;vertical-align:top;color:#111827;white-space:nowrap;">{{ \App\Support\Invoicing::money((int) ($it['amount_cents'] ?? 0), $cur) }}</td>
        </tr>
        @endforeach
      </table>
    </td></tr>

    {{-- 합계 --}}
    <tr><td style="padding:6px 24px 18px 24px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
        @if($inv->discount_cents > 0)
        <tr>
          <td align="right" style="padding:4px 10px;color:#6b7280;">소계 / Subtotal</td>
          <td align="right" style="padding:4px 10px;width:96px;white-space:nowrap;">{{ \App\Support\Invoicing::money((int) $inv->subtotal_cents, $cur) }}</td>
        </tr>
        <tr>
          <td align="right" style="padding:4px 10px;color:#6b7280;">할인 / Discount</td>
          <td align="right" style="padding:4px 10px;width:96px;white-space:nowrap;color:#dc2626;">-{{ \App\Support\Invoicing::money((int) $inv->discount_cents, $cur) }}</td>
        </tr>
        @endif
        <tr>
          <td align="right" style="padding:8px 10px;font-weight:800;color:#111827;border-top:2px solid #f59e0b;">합계 / Total</td>
          <td align="right" style="padding:8px 10px;width:96px;white-space:nowrap;font-size:18px;font-weight:800;color:#d97706;border-top:2px solid #f59e0b;">{{ \App\Support\Invoicing::money((int) $inv->total_cents, $cur) }}</td>
        </tr>
      </table>
      <div style="font-size:12px;color:#6b7280;padding:2px 10px 0 10px;text-align:right;">{{ $cur }} · 결제 완료 / Paid — {{ $payInfo['method'] ?? 'Card' }}@if(!empty($payInfo['paid_at'])) · {{ $payInfo['paid_at'] }}@endif</div>
    </td></tr>

    {{-- 회사 정보 --}}
    <tr><td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:16px 24px;font-size:12px;line-height:1.7;color:#6b7280;">
      <div style="font-weight:700;color:#374151;">{{ $company['name'] }}</div>
      @if($company['ceo'])<div>대표 / CEO: {{ $company['ceo'] }}</div>@endif
      @if($company['business_number'])<div>사업자 번호 / Business No.: {{ $company['business_number'] }}</div>@endif
      @if($company['address'])<div>{{ $company['address'] }}</div>@endif
      @if($company['phone'])<div>전화 / Tel: {{ $company['phone'] }}</div>@endif
      @if($company['email'])<div>문의 / Contact: {{ $company['email'] }}</div>@endif
      <div style="margin-top:6px;">이 인보이스는 결제 내역에 따라 자동으로 만들어졌어요. This invoice was generated automatically.</div>
    </td></tr>

  </table>

  @if($forWeb)
  <div class="no-print" style="max-width:600px;margin:14px auto 0 auto;text-align:center;">
    <button type="button" onclick="window.print()" style="min-height:44px;padding:0 22px;border:0;border-radius:10px;background:#111827;color:#ffffff;font-size:15px;font-weight:700;cursor:pointer;">인쇄하기 / Print</button>
  </div>
  @endif

</td></tr>
</table>
</body>
</html>
