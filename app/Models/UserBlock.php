<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBlock extends Model
{
    protected $fillable = ['blocker_id', 'blocked_id'];

    // ── Relationships ───────────────────────────────────────

    public function blocker()
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    public function blocked()
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }

    // ── Static Helpers ──────────────────────────────────────

    /**
     * Check if blocker has blocked the given user.
     */
    public static function isBlocked(int $blockerId, int $blockedId): bool
    {
        return self::where('blocker_id', $blockerId)
            ->where('blocked_id', $blockedId)
            ->exists();
    }
}
