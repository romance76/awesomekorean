<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 쿠폰에 일어난 일의 기록 — 발급·사용 요청·확인·거절·만료·연장·취소 요청·신고. 분쟁과 감사용이라 지우지 않는다. */
class GroupBuyCouponEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['coupon_id', 'type', 'actor_id', 'actor_kind', 'note', 'created_at'];
    protected $casts = ['created_at' => 'datetime'];
}
