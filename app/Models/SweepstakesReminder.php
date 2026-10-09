<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SweepstakesReminder extends Model
{
    protected $table = 'sweepstakes_reminders';

    protected $fillable = ['user_id', 'sweepstakes_id', 'remind_at', 'notified_at', 'dismissed_at'];

    protected $casts = [
        'remind_at' => 'datetime',
        'notified_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function sweepstakes()
    {
        return $this->belongsTo(Sweepstakes::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
