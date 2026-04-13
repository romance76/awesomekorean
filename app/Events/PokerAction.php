<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PokerAction implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $gameId,
        public array $state,
        public ?array $action = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('poker.' . $this->gameId)];
    }

    public function broadcastWith(): array
    {
        return [
            'gameId' => $this->gameId,
            'state' => $this->state,
            'action' => $this->action,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'game.action';
    }
}
