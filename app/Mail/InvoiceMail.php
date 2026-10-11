<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Support\Invoicing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * 결제 인보이스(영수증) 메일 — 한국어 + 영문 병기 HTML(+ 글자만 보는 메일용 텍스트본).
 * 같은 HTML 이 회원 "인보이스 보기"에도 쓰인다.
 * ShouldQueue 라 실수로 send() 해도 요청을 붙잡지 않고 큐로 간다. 결과 기록은 SendInvoiceEmail 작업이 한다.
 */
class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $invoiceId) {}

    public function envelope(): Envelope
    {
        $inv = Invoice::find($this->invoiceId);
        $no = $inv ? $inv->invoice_no : '';
        return new Envelope(subject: '[AwesomeKorean] 결제 인보이스 ' . $no . ' / Invoice');
    }

    public function content(): Content
    {
        $inv = Invoice::findOrFail($this->invoiceId);
        $data = Invoicing::viewData($inv, false);
        return new Content(htmlString: view('emails.invoice', $data)->render(), text: 'emails.invoice-text', with: $data);
    }
}
