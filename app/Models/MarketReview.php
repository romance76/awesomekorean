<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketReview extends Model
{
    protected $fillable = ['market_item_id','market_reservation_id','reviewer_id','reviewee_id','rating','comment'];
    public function item() { return $this->belongsTo(MarketItem::class, 'market_item_id'); }
    public function reservation() { return $this->belongsTo(MarketReservation::class, 'market_reservation_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewer_id'); }
    public function reviewee() { return $this->belongsTo(User::class, 'reviewee_id'); }
}
