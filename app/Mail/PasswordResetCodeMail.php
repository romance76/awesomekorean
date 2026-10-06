<?php
namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public string $code) {}
    public function envelope(): Envelope { return new Envelope(subject: '[AwesomeKorean] 비밀번호 재설정 코드'); }
    public function content(): Content {
        // 관리자 페이지에서 업로드한 로고가 있으면 그걸로, 없으면 기본 로고로 —
        // 메일 클라이언트는 상대경로 이미지를 못 읽으므로 절대 URL로 변환해서 전달.
        $logoPath = \App\Models\SiteSetting::where('key', 'logo_url')->value('value') ?: '/images/logo.png';
        return new Content(view: 'emails.password-reset-code', with: [
            'code' => $this->code,
            'logoUrl' => url($logoPath),
        ]);
    }
}
