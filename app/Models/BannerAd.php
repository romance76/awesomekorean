<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BannerAd extends Model
{
    protected $fillable = [
        'user_id', 'title', 'image_url', 'link_url',
        'page', 'target_pages', 'position', 'slot_number',
        'ad_type', 'phone', 'description',
        'geo_scope', 'geo_value',
        'start_date', 'end_date', 'auction_month', 'auction_rank',
        'daily_cost', 'total_cost', 'bid_amount',
        'impressions', 'clicks', 'status', 'reject_reason',
        'payment_method', 'payment_id',   // 'points'(예전 신청) | 'card'(2026-10-11 부터 광고는 카드 결제만)
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
