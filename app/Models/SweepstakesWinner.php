<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SweepstakesWinner extends Model
{
    protected $table = 'sweepstakes_winners';

    protected $fillable = [
        'sweepstakes_id', 'rank', 'user_id', 'winning_index',
        'total_entries_at_draw', 'prize_label', 'selection_method', 'selected_at',
    ];

    protected $casts = [
        'rank' => 'integer',
        'winning_index' => 'integer',
        'total_entries_at_draw' => 'integer',
        'selected_at' => 'datetime',
    ];

    public function sweepstakes()
    {
        return $this->belongsTo(Sweepstakes::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
