<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * WebRTC signaling for the comms (1:1 voice call) system.
 * Named CommWebRtcSignal to avoid conflict with the existing WebRTCSignal event (game system).
 */
class CommWebRtcSignal implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int    $targetUserId,
        public string $roomId,
        public string $type,
        public array  $payload
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->targetUserId)];
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->roomId,
            'type'    => $this->type,
            'payload' => $this->payload,
        ];
    }

    public function broadcastAs(): string
    {
        return 'webrtc.signal';
    }
}
