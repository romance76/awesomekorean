<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SweepstakesPrizeClaim extends Model
{
    protected $table = 'sweepstakes_prize_claims';

    protected $fillable = [
        'sweepstakes_id', 'user_id', 'rank', 'prize_label',
        'notified_at', 'popup_dismissed_at', 'contact_confirmed_at', 'contact_snapshot',
        'fulfilled_at', 'fulfilled_note',
    ];

    protected $casts = [
        'rank' => 'integer',
        'notified_at' => 'datetime',
        'popup_dismissed_at' => 'datetime',
        'contact_confirmed_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'contact_snapshot' => 'array',
    ];

    // 당첨자의 개인 연락처가 담기므로 일반 직렬화에서 숨김
    protected $hidden = ['contact_snapshot'];

    public function sweepstakes()
    {
        return $this->belongsTo(Sweepstakes::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
