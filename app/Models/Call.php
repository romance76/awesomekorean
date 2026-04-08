<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    protected $fillable = ['room_id', 'caller_id', 'callee_id', 'status'];
    protected $casts    = ['answered_at' => 'datetime', 'ended_at' => 'datetime'];

    // ── Relationships ───────────────────────────────────────

    public function caller()
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function callee()
    {
        return $this->belongsTo(User::class, 'callee_id');
    }

    // ── Actions ─────────────────────────────────────────────

    public function answer(): void
    {
        $this->update(['status' => 'answered', 'answered_at' => now()]);
    }

    public function end(): void
    {
        $duration = $this->answered_at ? now()->diffInSeconds($this->answered_at) : 0;
        $this->update(['status' => 'ended', 'ended_at' => now(), 'duration' => $duration]);
    }

    // ── Accessors ───────────────────────────────────────────

    public function getDurationFormattedAttribute(): string
    {
        return sprintf('%02d:%02d', intdiv($this->duration, 60), $this->duration % 60);
    }
}
