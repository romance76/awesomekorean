<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Payment extends Model
{
    public const KIND_POINTS = 'points';
    public const KIND_EVENT_REQUEST = 'event_request';
    public const KIND_FLYER = 'flyer';
    /** 달러 직접 결제 종류 (포인트 구매가 아닌 것) */
    public const DIRECT_KINDS = [self::KIND_EVENT_REQUEST, self::KIND_FLYER];

    protected $fillable = ['user_id','kind','ref_type','ref_id','stripe_payment_id','amount','refunded_amount','currency','description','points_purchased','status','captured_at'];
    protected $casts = ['amount'=>'decimal:2','refunded_amount'=>'decimal:2','captured_at'=>'datetime'];
    public function user() { return $this->belongsTo(User::class); }
}
