<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchProfile extends Model
{
    protected $fillable = [
        'user_id','nickname','gender','birth_year',
        'age_range_min','age_range_max','region',
        'bio','interests','photos','verified','visibility',
    ];

    protected $casts = [
        'interests' => 'array',
        'photos'    => 'array',
        'verified'  => 'boolean',
    ];

    public function user() { return $this->belongsTo(User::class); }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_year ? (date('Y') - $this->birth_year) : null;
    }
}
