<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'user_id','title','description','location','region',
        'category','max_attendees','attendee_count','price',
        'image','event_date','is_online','status',
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'is_online'  => 'boolean',
        'price'      => 'decimal:2',
    ];

    public function organizer() { return $this->belongsTo(User::class, 'user_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
