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
    public function scopeNearby($q,$lat,$lng,$r=50) { return $q->addSelect(\Illuminate\Support\Facades\DB::raw("(3959*acos(cos(radians({$lat}))*cos(radians(lat))*cos(radians(lng)-radians({$lng}))+sin(radians({$lat}))*sin(radians(lat)))) AS distance"))->having('distance','<',$r); }
}
