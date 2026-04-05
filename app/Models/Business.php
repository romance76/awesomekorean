<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Business extends Model
{
    protected $fillable = ['user_id','name','description','category','subcategory','phone','email','website','address','city','state','zipcode','lat','lng','images','logo','hours','rating','review_count','is_verified','is_claimed','owner_id','view_count'];
    protected $casts = ['images'=>'array','hours'=>'array','rating'=>'decimal:2','is_verified'=>'boolean','is_claimed'=>'boolean','lat'=>'decimal:7','lng'=>'decimal:7'];
    public function user() { return $this->belongsTo(User::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function reviews() { return $this->hasMany(BusinessReview::class); }
    public function claims() { return $this->hasMany(BusinessClaim::class); }
    public function scopeNearby($q,$lat,$lng,$r=50) { return $q->selectRaw("*, (3959*acos(cos(radians(?))*cos(radians(lat))*cos(radians(lng)-radians(?))+sin(radians(?))*sin(radians(lat)))) AS distance",[$lat,$lng,$lat])->having('distance','<',$r)->orderBy('distance'); }
}
