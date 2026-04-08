<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Only conversation participants can view the conversation.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return in_array($user->id, [$conversation->user_a_id, $conversation->user_b_id]);
    }
}
