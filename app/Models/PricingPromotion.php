<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingPromotion extends Model
{
    protected $fillable = [
        'title','discount_pct','applies_to_ads','applies_to_packages',
        'starts_at','ends_at','is_active',
    ];

    protected $casts = [
        'discount_pct'          => 'integer',
        'applies_to_ads'        => 'boolean',
        'applies_to_packages'   => 'boolean',
        'is_active'             => 'boolean',
        'starts_at'             => 'datetime',
        'ends_at'               => 'datetime',
    ];

    public function scopeActiveNow($q)
    {
        $now = now();
        return $q->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now);
    }

    /** 주어진 대상(ad|package)에 현재 적용되는 최대 할인 % 반환, 없으면 0 */
    public static function currentDiscount(string $target): int
    {
        $col = $target === 'ad' ? 'applies_to_ads' : 'applies_to_packages';
        $promo = static::activeNow()->where($col, true)->orderByDesc('discount_pct')->first();
        return $promo ? (int) $promo->discount_pct : 0;
    }

    /** 현재 활성 프로모션 객체 (UI 표시용) */
    public static function currentFor(string $target): ?self
    {
        $col = $target === 'ad' ? 'applies_to_ads' : 'applies_to_packages';
        return static::activeNow()->where($col, true)->orderByDesc('discount_pct')->first();
    }
}
