<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RealEstateListing extends Model
{
    protected $fillable = ['user_id','title','content','type','property_type','price','deposit','images','address','city','state','zipcode','lat','lng','bedrooms','bathrooms','sqft','is_active','view_count','contact_phone','contact_email','promotion_tier','promotion_expires_at','promotion_states'];
    protected $casts = ['images'=>'array','price'=>'decimal:2','deposit'=>'decimal:2','is_active'=>'boolean','lat'=>'decimal:7','lng'=>'decimal:7','promotion_expires_at'=>'datetime','promotion_states'=>'array'];
    public function user() { return $this->belongsTo(User::class); }
    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeNearby($q,$lat,$lng,$r=50) { return $q->selectRaw("*, (3959*acos(cos(radians(?))*cos(radians(lat))*cos(radians(lng)-radians(?))+sin(radians(?))*sin(radians(lat)))) AS distance",[$lat,$lng,$lat])->having('distance','<',$r); }
}
