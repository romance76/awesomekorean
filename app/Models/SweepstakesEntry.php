<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SweepstakesEntry extends Model
{
    protected $fillable = ['sweepstakes_id', 'user_id', 'entries_count'];

    protected $casts = [
        'entries_count' => 'integer',
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
