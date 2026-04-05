<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketItem extends Model
{
    protected $fillable = ['user_id','title','content','price','images','category','condition','lat','lng','city','state','status','view_count','is_negotiable'];
    protected $casts = ['images'=>'array','price'=>'decimal:2','is_negotiable'=>'boolean','lat'=>'decimal:7','lng'=>'decimal:7'];
    public function user() { return $this->belongsTo(User::class); }
    public function reservations() { return $this->hasMany(MarketReservation::class); }
    public function comments() { return $this->morphMany(Comment::class, 'commentable'); }
    public function scopeNearby($q,$lat,$lng,$r=50) { return $q->selectRaw("*, (3959*acos(cos(radians(?))*cos(radians(lat))*cos(radians(lng)-radians(?))+sin(radians(?))*sin(radians(lat)))) AS distance",[$lat,$lng,$lat])->having('distance','<',$r)->orderBy('distance'); }
}
