<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * 월요일 정기 소식 메일 (App\Support\WeeklyDigest 가 내용을 만든다). 한국어·영어 두 버전(data['lang']).
 * 수신 거부 링크를 본문 맨 아래와 메일 헤더(List-Unsubscribe, 원클릭)에 함께 넣는다 — Gmail·Yahoo 대량 발송 기준.
 */
class WeeklyDigestMail extends Mailable
{
    public function __construct(public array $data) {}

    public function envelope(): Envelope
    {
        $t = $this->data['t'];
        $prefix = !empty($this->data['isTest']) ? $t['test_prefix'] : '';
        return new Envelope(subject: $prefix . str_replace(':date', $this->data['dateLabel'], $t['subject_digest']));
    }

    public function headers(): Headers
    {
        $u = empty($this->data['unsubIsSample']) ? ($this->data['unsubscribeUrl'] ?? null) : null;   // 시험용 링크는 헤더에 넣지 않는다
        return new Headers(text: $u ? [
            'List-Unsubscribe' => '<' . $u . '>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            'Precedence' => 'bulk',
        ] : ['Precedence' => 'bulk']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.weekly-digest', with: $this->data);
    }
}
