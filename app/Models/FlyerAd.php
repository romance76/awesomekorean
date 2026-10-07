<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlyerAd extends Model
{
    protected $fillable = [
        'user_id', 'title', 'kind', 'description', 'phone', 'link_url', 'image_url',
        'scope', 'region_key', 'status', 'reject_reason', 'total_price', 'hours_count',
        'start_date', 'end_date', 'approved_at',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'approved_at' => 'datetime',
        'total_price' => 'integer',
        'hours_count' => 'integer',
        'view_count' => 'integer',
        'click_count' => 'integer',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function slots() { return $this->hasMany(FlyerSlot::class); }
}
