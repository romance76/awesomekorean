<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 공동구매 쿠폰 한 장. 상태: unused(미사용) / pending(사용 확인 중) / used(사용) / expired(만료) / cancelled(취소) */
class GroupBuyCoupon extends Model
{
    protected $fillable = [
        'group_buy_id', 'participant_id', 'user_id', 'seq', 'code', 'confirm_code', 'status', 'expires_at', 'reminder_sent_at',
        'pending_mode', 'pending_via', 'pending_at', 'pending_expires_at', 'used_at', 'used_mode', 'used_via',
        'undo_requested_at', 'undo_requested_by', 'denied_count',
    ];
    protected $casts = [
        'expires_at' => 'datetime', 'reminder_sent_at' => 'datetime', 'pending_at' => 'datetime', 'pending_expires_at' => 'datetime',
        'used_at' => 'datetime', 'undo_requested_at' => 'datetime',
    ];
    // 고객 확인 코드는 쿠폰 주인에게만 보여 준다 — 응답에 실수로 실리지 않게 기본으로 숨김
    protected $hidden = ['confirm_code'];

    public function groupBuy() { return $this->belongsTo(GroupBuy::class); }
    public function holder() { return $this->belongsTo(User::class, 'user_id'); }
    public function participant() { return $this->belongsTo(GroupBuyParticipant::class, 'participant_id'); }
    public function events() { return $this->hasMany(GroupBuyCouponEvent::class, 'coupon_id'); }

    /** 화면에 보여 줄 번호: ABCDE-FGHJK */
    public function getDisplayCodeAttribute(): string
    {
        return strlen($this->code) === 10 ? substr($this->code, 0, 5) . '-' . substr($this->code, 5) : $this->code;
    }
}
