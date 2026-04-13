<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PokerChat implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $gameId,
        public int $userId,
        public string $userName,
        public string $message,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('poker.' . $this->gameId)];
    }

    public function broadcastWith(): array
    {
        return [
            'userId' => $this->userId,
            'userName' => $this->userName,
            'message' => $this->message,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'game.chat';
    }
}
