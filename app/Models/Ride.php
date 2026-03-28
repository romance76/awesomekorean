<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ride extends Model
{
    protected $fillable = [
        'passenger_id','driver_id','status',
        'pickup_lat','pickup_lng','pickup_address',
        'dropoff_lat','dropoff_lng','dropoff_address',
        'estimated_fare','final_fare','platform_fee',
        'payment_method','rating_driver','rating_passenger',
        'distance_miles','requested_at','matched_at','started_at','completed_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'matched_at'   => 'datetime',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function passenger() { return $this->belongsTo(User::class, 'passenger_id'); }
    public function driver()    { return $this->belongsTo(User::class, 'driver_id'); }
    public function reviews()   { return $this->hasMany(RideReview::class); }
}
