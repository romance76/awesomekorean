<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoardSuspension extends Model
{
    protected $fillable = ['user_id', 'board_key', 'board_label', 'report_id', 'reason', 'ends_at', 'lifted_at', 'lifted_by', 'created_by'];

    protected $casts = ['ends_at' => 'datetime', 'lifted_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }

    /** 지금도 효력이 있는 정지인지 (풀리지 않았고, 영구이거나 아직 끝나지 않음) */
    public function isActive(): bool
    {
        return $this->lifted_at === null && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    public function scopeActive($q)
    {
        return $q->whereNull('lifted_at')->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
