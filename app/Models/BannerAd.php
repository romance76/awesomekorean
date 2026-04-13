<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BannerAd extends Model
{
    protected $fillable = [
        'user_id', 'title', 'image_url', 'link_url',
        'page', 'target_pages', 'position', 'slot_number',
        'geo_scope', 'geo_value',
        'start_date', 'end_date', 'auction_month', 'auction_rank',
        'daily_cost', 'total_cost', 'bid_amount',
        'impressions', 'clicks', 'status', 'reject_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'target_pages' => 'array',
    ];

    public function user() { return $this->belongsTo(User::class); }

    public function scopeActive($q) {
        return $q->where('status', 'active')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }
}
