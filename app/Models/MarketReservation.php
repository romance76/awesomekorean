<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketReservation extends Model
{
    protected $fillable = ['market_item_id','buyer_id','seller_id','points_held','hold_until','meetup_at','meetup_place','hold_hours','commission','seller_received','status','completed_at','cancelled_at'];
    protected $casts = ['completed_at'=>'datetime','cancelled_at'=>'datetime','hold_until'=>'datetime','meetup_at'=>'datetime'];
    public function item() { return $this->belongsTo(MarketItem::class, 'market_item_id'); }
    public function buyer() { return $this->belongsTo(User::class, 'buyer_id'); }
    public function seller() { return $this->belongsTo(User::class, 'seller_id'); }
}
