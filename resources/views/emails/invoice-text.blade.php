{!! $company['name'] !!} - 결제 인보이스 / Invoice
{!! str_repeat('-', 40) !!}
인보이스 번호 / Invoice No.: {!! $inv->invoice_no !!}
발행일 / Issued: {!! $issuedText !!}
받는 분 / Billed to: {!! $inv->billing_name !!} <{!! $inv->billing_email !!}>
@if($inv->status === 'void')
*** 무효 처리된 인보이스예요 / VOID ***
@endif

@foreach($items as $it)
- {!! $it['name'] ?? '' !!} x{!! (int) ($it['qty'] ?? 1) !!}  {!! \App\Support\Invoicing::money((int) ($it['amount_cents'] ?? 0), $cur) !!}
@endforeach

합계 / Total: {!! \App\Support\Invoicing::money((int) $inv->total_cents, $cur) !!} ({!! $cur !!})
{!! str_repeat('-', 40) !!}
{!! $company['name'] !!}
@if($company['address']){!! $company['address'] !!}
@endif
@if($company['email'])문의 / Contact: {!! $company['email'] !!}
@endif
