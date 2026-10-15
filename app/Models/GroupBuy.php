<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GroupBuy extends Model
{
    protected $fillable = ['user_id','title','content','images','product_url','business_doc','original_price','group_price','discount_tiers','min_participants','max_participants','current_participants','lat','lng','city','state','status','end_type','payment_method','is_approved','approved_by','approved_at','rejection_reason','category','deadline'];
    protected $casts = ['images'=>'array','discount_tiers'=>'array','deadline'=>'datetime','approved_at'=>'datetime','lat'=>'decimal:7','lng'=>'decimal:7','original_price'=>'decimal:2','group_price'=>'decimal:2','is_approved'=>'boolean'];
    protected $appends = ['display_status'];
    // 화면에 보이는 상태 — 모집중인데 마감일이 지났으면 'ended'(마감). DB의 status 는 그대로 둠(환불·정산은 별도 승인 사항)
    public function getDisplayStatusAttribute() {
        if (($this->attributes['status'] ?? null) === 'recruiting' && $this->deadline && $this->deadline->isPast()) return 'ended';
        return $this->attributes['status'] ?? null;
    }
    public function scopeStillOpen($q) { return $q->where(fn($w) => $w->whereNull('deadline')->orWhere('deadline', '>', now())); }
    public function scopeDeadlinePassed($q) { return $q->where('status', 'recruiting')->whereNotNull('deadline')->where('deadline', '<=', now()); }
    public function user() { return $this->belongsTo(User::class); }
    public function participants() { return $this->hasMany(GroupBuyParticipant::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function scopeNearby($q,$lat,$lng,$r=50) { return $q->selectRaw("*, (3959*acos(cos(radians(?))*cos(radians(lat))*cos(radians(lng)-radians(?))+sin(radians(?))*sin(radians(lat)))) AS distance",[$lat,$lng,$lat])->having('distance','<',$r); }
    public function scopeApproved($q) { return $q->where('is_approved', true); }
    // 현재 할인율 계산
    public function getCurrentDiscountAttribute() {
        if (!$this->discount_tiers) return 0;
        $tiers = collect($this->discount_tiers)->sortByDesc('min_people');
        foreach ($tiers as $tier) {
            if ($this->current_participants >= $tier['min_people']) return $tier['discount_pct'];
        }
        return 0;
    }
    public function getCurrentPriceAttribute() {
        $discount = $this->current_discount;
        return $discount > 0 ? round($this->original_price * (100 - $discount) / 100, 2) : ($this->group_price ?: $this->original_price);
    }
}
