<?php
namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClaimApprovedMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public string $businessName, public string $userName) {}
    public function envelope(): Envelope { return new Envelope(subject: '[AwesomeKorean] 업소 소유권 신청이 승인되었습니다'); }
    public function content(): Content {
        return new Content(view: 'emails.claim-approved', with: [
            'businessName' => $this->businessName, 'userName' => $this->userName,
            'dashboardUrl' => config('app.url') . '/my-business'
        ]);
    }
}
