<?php

namespace App\Events;

use App\Models\PokerTournament;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PokerTournamentStart implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PokerTournament $tournament,
        public string $gameId,
        public array $playerIds,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('poker.tournament.' . $this->tournament->id),
            new Channel('poker.lobby'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'tournament.started';
    }

    public function broadcastWith(): array
    {
        return [
            'tournament_id' => $this->tournament->id,
            'title' => $this->tournament->title,
            'game_id' => $this->gameId,
            'player_ids' => $this->playerIds,
            'status' => 'running',
        ];
    }
}
