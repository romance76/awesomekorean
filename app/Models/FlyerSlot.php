<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlyerSlot extends Model
{
    protected $fillable = ['flyer_ad_id', 'region_key', 'slot_date', 'slot_hour', 'price'];

    protected $casts = [
        'slot_date' => 'date:Y-m-d',
        'slot_hour' => 'integer',
        'price' => 'integer',
    ];

    public function flyer() { return $this->belongsTo(FlyerAd::class, 'flyer_ad_id'); }
}
