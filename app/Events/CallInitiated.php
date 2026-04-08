<?php

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallInitiated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Call $call) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->call->callee_id)];
    }

    public function broadcastWith(): array
    {
        return [
            'call_id'       => $this->call->id,
            'room_id'       => $this->call->room_id,
            'caller_id'     => $this->call->caller_id,
            'caller_name'   => $this->call->caller->name,
            'caller_avatar' => $this->call->caller->avatar,
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.initiated';
    }
}
