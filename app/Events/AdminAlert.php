<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * 실시간 관리자 알림 (Phase 2-C Post).
 * Reverb 'admin-alerts' public 채널로 브로드캐스트 → 관리자 화면 토스트/뱃지.
 */
class AdminAlert implements ShouldBroadcast
{
    use Dispatchable;

    public string  $type;       // 'report' | 'sos' | 'payment_failed' | 'auto_ban' | 'signup_spike'
    public string  $title;
    public string  $message;
    public ?string $link;
    public string  $severity;   // 'info' | 'warning' | 'critical'

    public function __construct(string $type, string $title, string $message, ?string $link = null, string $severity = 'info')
    {
        $this->type     = $type;
        $this->title    = $title;
        $this->message  = $message;
        $this->link     = $link;
        $this->severity = $severity;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('admin-alerts');
    }

    public function broadcastAs(): string
    {
        return 'admin.alert';
    }

    public function broadcastWith(): array
    {
        return [
            'type'       => $this->type,
            'title'      => $this->title,
            'message'    => $this->message,
            'link'       => $this->link,
            'severity'   => $this->severity,
            'occurred_at'=> now()->toIso8601String(),
        ];
    }
}
