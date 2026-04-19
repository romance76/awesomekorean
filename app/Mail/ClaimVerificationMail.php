<?php
namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClaimVerificationMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public string $businessName, public string $verifyUrl, public string $userName) {}
    public function envelope(): Envelope { return new Envelope(subject: '[AwesomeKorean] 업소 이메일 인증'); }
    public function content(): Content {
        return new Content(view: 'emails.claim-verification', with: [
            'businessName' => $this->businessName, 'verifyUrl' => $this->verifyUrl, 'userName' => $this->userName
        ]);
    }
}
