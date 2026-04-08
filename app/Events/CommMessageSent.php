<?php

namespace App\Events;

use App\Models\CommMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a new 1:1 comm message is sent.
 * Named CommMessageSent to avoid conflict with the existing MessageSent event (chat rooms).
 */
class CommMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public CommMessage $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.' . $this->message->conversation_id)];
    }

    public function broadcastWith(): array
    {
        return [
            'id'              => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id'       => $this->message->sender_id,
            'sender_name'     => $this->message->sender->name,
            'sender_avatar'   => $this->message->sender->avatar,
            'body'            => $this->message->body,
            'type'            => $this->message->type,
            'created_at'      => $this->message->created_at->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'comm.message.sent';
    }
}
