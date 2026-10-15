<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Business extends Model
{
    protected $fillable = ['google_place_id','user_id','name','description','category','subcategory','phone','email','website','address','city','state','zipcode','lat','lng','images','logo','hours','rating','review_count','google_reviews','is_verified','is_claimed','owner_id','view_count','promotion_tier','promotion_expires_at','promotion_states'];
    protected $casts = ['images'=>'array','hours'=>'array','google_reviews'=>'array','rating'=>'decimal:2','is_verified'=>'boolean','is_claimed'=>'boolean','lat'=>'decimal:7','lng'=>'decimal:7','promotion_expires_at'=>'datetime','promotion_states'=>'array'];
    public function user() { return $this->belongsTo(User::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function reviews() { return $this->hasMany(BusinessReview::class); }
    public function claims() { return $this->hasMany(BusinessClaim::class); }
    public function menus() { return $this->hasMany(BusinessMenu::class); }
    // 공개 화면(업소록 목록·검색·상세)에 보여도 되는 업소 — 운영자가 숨긴(hidden_at) 업소 제외. 삭제가 아니라 숨김이라 hidden_at 을 비우면 다시 보인다.
    // 배포 직후 마이그레이션이 끝나기 전 몇 초 동안 컬럼이 없어도 오류가 나지 않게, 컬럼이 있을 때만 거른다(있다고 확인되면 계속 기억).
    public function scopeVisible($q)
    {
        $has = \Illuminate\Support\Facades\Cache::get('biz_hidden_col')
            ?: (\Illuminate\Support\Facades\Schema::hasColumn('businesses', 'hidden_at') && \Illuminate\Support\Facades\Cache::forever('biz_hidden_col', true));
        return $has ? $q->whereNull('businesses.hidden_at') : $q;
    }
    public function scopeNearby($q,$lat,$lng,$r=50) { return $q->addSelect(\Illuminate\Support\Facades\DB::raw("(3959*acos(cos(radians({$lat}))*cos(radians(lat))*cos(radians(lng)-radians({$lng}))+sin(radians({$lat}))*sin(radians(lat)))) AS distance"))->having('distance','<',$r); }
}
