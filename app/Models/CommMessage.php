<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CommMessage — 1:1 안심 커뮤니케이션 메시지.
 * Uses table 'comm_messages' to avoid conflict with the existing 'messages' table.
 */
class CommMessage extends Model
{
    use SoftDeletes;

    protected $table = 'comm_messages';

    protected $fillable = ['conversation_id', 'sender_id', 'body', 'type'];
    protected $casts    = ['read_at' => 'datetime'];

    // ── Relationships ───────────────────────────────────────

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    // ── Helpers ─────────────────────────────────────────────

    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }
}
