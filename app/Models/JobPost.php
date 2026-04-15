<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JobPost extends Model
{
    protected $fillable = ['user_id','post_type','title','company','logo','content','category','job_tags','benefits','company_pdf','type','salary_min','salary_max','salary_type','lat','lng','city','state','zipcode','contact_email','contact_phone','is_active','expires_at','view_count','promotion_tier','promotion_expires_at','promotion_states'];
    protected $casts = [
        'is_active'=>'boolean','expires_at'=>'datetime','promotion_expires_at'=>'datetime',
        'lat'=>'decimal:7','lng'=>'decimal:7',
        'job_tags'=>'array','benefits'=>'array','promotion_states'=>'array',
    ];
    public function user() { return $this->belongsTo(User::class); }
    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeHiring($q) { return $q->where('post_type', 'hiring'); }
    public function scopeSeeking($q) { return $q->where('post_type', 'seeking'); }
    public function scopeNearby($q,$lat,$lng,$r=50) { return $q->selectRaw("*, (3959*acos(cos(radians(?))*cos(radians(lat))*cos(radians(lng)-radians(?))+sin(radians(?))*sin(radians(lat)))) AS distance",[$lat,$lng,$lat])->having('distance','<',$r); }

    // 프로모션이 아직 유효한지
    public function getIsPromotedAttribute() {
        return $this->promotion_tier !== 'none' && $this->promotion_expires_at && $this->promotion_expires_at->isFuture();
    }

    // 프로모션 자동 만료 처리용 scope
    public function scopeWithActivePromotion($q) {
        return $q->where('promotion_tier', '!=', 'none')->where('promotion_expires_at', '>', now());
    }
}
