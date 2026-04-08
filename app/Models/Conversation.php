<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Conversation extends Model
{
    protected $fillable = ['user_a_id', 'user_b_id', 'last_message_at'];
    protected $casts    = ['last_message_at' => 'datetime'];

    /**
     * Find existing conversation or create a new one between two users.
     * Always stores the smaller user ID as user_a_id for uniqueness.
     */
    public static function findOrCreateBetween(int $userA, int $userB): self
    {
        [$a, $b] = $userA < $userB ? [$userA, $userB] : [$userB, $userA];
        return self::firstOrCreate(['user_a_id' => $a, 'user_b_id' => $b]);
    }

    // ── Relationships ───────────────────────────────────────

    public function messages()
    {
        return $this->hasMany(CommMessage::class)->latest();
    }

    public function latestMessage()
    {
        return $this->hasOne(CommMessage::class)->latestOfMany();
    }

    public function userA()
    {
        return $this->belongsTo(User::class, 'user_a_id');
    }

    public function userB()
    {
        return $this->belongsTo(User::class, 'user_b_id');
    }

    // ── Helpers ─────────────────────────────────────────────

    /**
     * Return the other participant in the conversation.
     */
    public function otherUser(int $myId): User
    {
        return $this->user_a_id === $myId ? $this->userB : $this->userA;
    }

    /**
     * Count unread messages for a given user.
     */
    public function unreadCount(int $myId): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $myId)
            ->whereNull('read_at')
            ->count();
    }
}
