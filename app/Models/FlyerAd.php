<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlyerAd extends Model
{
    protected $fillable = [
        'user_id', 'title', 'kind', 'description', 'phone', 'link_url', 'image_url',
        'scope', 'region_key', 'extra_states', 'status', 'reject_reason', 'total_price', 'payment_method', 'payment_id', 'hours_count',
        'start_date', 'end_date', 'approved_at', 'topup_cents', 'credit_used_cents',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'approved_at' => 'datetime',
        'extra_states' => 'array',
        'total_price' => 'integer',
        'topup_cents' => 'integer',
        'credit_used_cents' => 'integer',
        'hours_count' => 'integer',
        'view_count' => 'integer',
        'click_count' => 'integer',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function slots() { return $this->hasMany(FlyerSlot::class); }
}
