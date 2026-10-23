<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * 전면광고 광고주 특권 메일(무료) — 승인된 전면광고를 수신 동의 회원에게 알린다(qa/EMAIL_AD_PLAN.md 3-A).
 * 내용은 App\Support\WeeklyDigest::flyerPayload 가 만든다. 지금은 관리자 미리보기·시험 발송까지만 연결돼 있다.
 */
class FlyerAdMail extends Mailable
{
    public function __construct(public array $data) {}

    public function envelope(): Envelope
    {
        $prefix = !empty($this->data['isTest']) ? '[시험] ' : '';
        return new Envelope(subject: $prefix . '[어썸코리안 광고] ' . $this->data['kindLabel'] . ' · ' . $this->data['title']);
    }

    public function headers(): Headers
    {
        $u = $this->data['unsubscribeUrl'] ?? null;
        return new Headers(text: $u ? [
            'List-Unsubscribe' => '<' . $u . '>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            'Precedence' => 'bulk',
        ] : ['Precedence' => 'bulk']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.flyer-ad', with: $this->data);
    }
}
