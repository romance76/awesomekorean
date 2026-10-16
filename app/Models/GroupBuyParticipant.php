<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupBuyParticipant extends Model
{
    protected $fillable = ['group_buy_id', 'user_id', 'quantity', 'paid_amount', 'payment_type', 'payment_id', 'status', 'consent_at'];
    protected $casts = ['consent_at' => 'datetime'];

    public function groupBuy() { return $this->belongsTo(GroupBuy::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function coupons() { return $this->hasMany(GroupBuyCoupon::class, 'participant_id'); }
}
