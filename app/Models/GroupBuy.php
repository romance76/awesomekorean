<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GroupBuy extends Model
{
    protected $fillable = ['user_id','title','content','images','product_url','original_price','group_price','min_participants','max_participants','current_participants','lat','lng','city','state','status','deadline'];
    protected $casts = ['images'=>'array','deadline'=>'datetime','lat'=>'decimal:7','lng'=>'decimal:7','original_price'=>'decimal:2','group_price'=>'decimal:2'];
    public function user() { return $this->belongsTo(User::class); }
    public function scopeNearby($q,$lat,$lng,$r=50) { return $q->selectRaw("*, (3959*acos(cos(radians(?))*cos(radians(lat))*cos(radians(lng)-radians(?))+sin(radians(?))*sin(radians(lat)))) AS distance",[$lat,$lng,$lat])->having('distance','<',$r)->orderBy('distance'); }
}
