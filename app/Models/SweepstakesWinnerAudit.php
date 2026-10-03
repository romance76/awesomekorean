<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SweepstakesWinnerAudit extends Model
{
    protected $fillable = [
        'sweepstakes_id', 'winner_user_id', 'winning_index',
        'total_entries_at_draw', 'selection_method', 'selected_at',
    ];

    protected $casts = [
        'winning_index' => 'integer',
        'total_entries_at_draw' => 'integer',
        'selected_at' => 'datetime',
    ];

    public function sweepstakes()
    {
        return $this->belongsTo(Sweepstakes::class);
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }
}
