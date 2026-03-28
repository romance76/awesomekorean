<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverProfile extends Model
{
    protected $fillable = [
        'user_id','license_number','license_img',
        'car_make','car_model','car_year','car_color','car_plate',
        'verified','is_online','current_lat','current_lng',
        'last_location_at','rating_avg','total_rides','total_earnings',
    ];

    protected $casts = [
        'verified'         => 'boolean',
        'is_online'        => 'boolean',
        'last_location_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
