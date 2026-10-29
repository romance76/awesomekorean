<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * 손님 메일(소개 메일) — App\Support\Outreach 가 내용을 만든다.
 * 수신 거부 링크를 본문 맨 아래와 메일 헤더(List-Unsubscribe, 원클릭)에 함께 넣는다 — Gmail·Yahoo 대량 발송 기준.
 */
class OutreachMail extends Mailable
{
    public function __construct(public array $data) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->data['isTest'] ? '[TEST] ' : '') . $this->data['subject']);
    }

    public function headers(): Headers
    {
        $u = $this->data['unsubscribeApi'] ?? null;
        return new Headers(text: [
            'List-Unsubscribe' => '<' . $u . '>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            'Precedence' => 'bulk',
        ]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.outreach', with: $this->data);
    }
}
