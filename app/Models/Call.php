<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    protected $fillable = [
        'room_id', 'caller_id', 'callee_id', 'call_type', 'status', 'answered_at', 'ended_at', 'duration',
        'end_reason', 'ended_by', 'caller_device', 'callee_device', 'conn_type', 'rtt_ms', 'failure_note', 'caller_ua', 'callee_ua',
    ];
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

    public function answer(?string $device = null, ?string $ua = null): void
    {
        $this->update(['status' => 'answered', 'answered_at' => now(), 'callee_device' => $device, 'callee_ua' => $ua]);
    }

    /**
     * 통화 종료. 상태/사유를 함께 남긴다 (관리자 통화 로그용).
     * 받기 전에 끝나면: 받는 사람이 끊음=declined, 거는 사람이 끊음=missed(응답 없음)·cancelled(취소)
     */
    public function end(?string $reason = null, ?int $endedBy = null, ?string $note = null): void
    {
        $duration = $this->answered_at ? max(0, now()->diffInSeconds($this->answered_at, true)) : 0;
        $status = 'ended';
        if (!$this->answered_at) {
            $status = match ($reason) {
                'declined' => 'declined',
                'failed'   => 'failed',
                'cancelled' => 'ended',
                default    => 'missed',   // no_answer / offline / busy / stale
            };
        } elseif ($reason === 'failed') {
            $status = 'failed';
        }
        $this->update([
            'status' => $status, 'ended_at' => now(), 'duration' => (int) $duration,
            'end_reason' => $reason ?: ($this->answered_at ? 'completed' : 'cancelled'),
            'ended_by' => $endedBy, 'failure_note' => $note ? mb_substr($note, 0, 255) : $this->failure_note,
        ]);
    }

    public function isActive(): bool { return in_array($this->status, ['ringing', 'answered'], true); }

    // ── Accessors ───────────────────────────────────────────

    public function getDurationFormattedAttribute(): string
    {
        return sprintf('%02d:%02d', intdiv($this->duration, 60), $this->duration % 60);
    }
}
