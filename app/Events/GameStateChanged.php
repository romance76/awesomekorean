<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameStateChanged implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        private int $roomId,
        private string $event,
        private array $data
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("game.{$this->roomId}");
    }

    public function broadcastAs(): string
    {
        return 'state.changed';
    }

    public function broadcastWith(): array
    {
        return ['event' => $this->event, 'data' => $this->data];
    }
}
