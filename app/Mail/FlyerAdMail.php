<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * 전면광고 광고주 특권 메일(무료) — 승인된 전면광고를 수신 동의 회원에게 알린다(qa/EMAIL_AD_PLAN.md 3-A). 한국어·영어 두 버전.
 * 내용은 App\Support\WeeklyDigest::flyerPayload 가 만든다. 지금은 관리자 미리보기·시험 발송까지만 연결돼 있다.
 */
class FlyerAdMail extends Mailable
{
    public function __construct(public array $data) {}

    public function envelope(): Envelope
    {
        $t = $this->data['t'];
        $prefix = !empty($this->data['isTest']) ? $t['test_prefix'] : '';
        return new Envelope(subject: $prefix . str_replace([':kind', ':title'], [$this->data['kindLabel'], $this->data['title']], $t['subject_flyer']));
    }

    public function headers(): Headers
    {
        $u = empty($this->data['unsubIsSample']) ? ($this->data['unsubscribeUrl'] ?? null) : null;
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
