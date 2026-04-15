<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobPromotion extends Model
{
    protected $fillable = ['job_post_id','user_id','tier','states','days','daily_cost','total_cost','starts_at','expires_at','status'];
    protected $casts = [
        'states' => 'array',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function jobPost() { return $this->belongsTo(JobPost::class); }
    public function user() { return $this->belongsTo(User::class); }

    public function scopeActive($q) { return $q->where('status', 'active')->where('expires_at', '>', now()); }

    // 주별 가격
    public static function pricePerDay($tier) {
        return match($tier) {
            'national' => 100,
            'state_plus' => 50,
            'sponsored' => 20,
            default => 0,
        };
    }

    // 최대 슬롯 수
    const MAX_SLOTS = 5;
}
