<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntryCheckin extends Model
{
    protected $fillable = ['user_id', 'checkin_date'];

    protected $casts = [
        'checkin_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
