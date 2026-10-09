<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sweepstakes extends Model
{
    protected $table = 'sweepstakes';

    protected $fillable = [
        'event_id', 'title', 'description', 'prize_name', 'prize_value', 'prize_image',
        'start_at', 'end_at', 'status',
        'minimum_age', 'eligible_regions', 'official_rules_url',
        'no_purchase_required_text', 'terms_version',
        'draw_style', 'theme',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'winner_selected_at' => 'datetime',
        'prize_value' => 'decimal:2',
        'total_entries' => 'integer',
        'minimum_age' => 'integer',
        'eligible_regions' => 'array',
        'theme' => 'array',
    ];

    public function entries()
    {
        return $this->hasMany(SweepstakesEntry::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    public function winnerAudit()
    {
        return $this->hasOne(SweepstakesWinnerAudit::class);
    }

    public function isOpenForEntries(): bool
    {
        return $this->status === 'active'
            && now()->between($this->start_at, $this->end_at);
    }
}
