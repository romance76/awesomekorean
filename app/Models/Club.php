<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Club extends Model
{
    protected $fillable = ['user_id','name','description','rules','category','image','cover_image','chat_room_id','type','zipcode','city','state','lat','lng','member_count','max_members','is_active','is_public','promotion_tier','promotion_expires_at','promotion_states','boosted_until'];
    protected $casts = ['is_active'=>'boolean','is_public'=>'boolean','lat'=>'decimal:7','lng'=>'decimal:7','promotion_expires_at'=>'datetime','boosted_until'=>'datetime','promotion_states'=>'array'];
    public function scopeNearby($q,$lat,$lng,$r=50) { return $q->selectRaw("*, (3959*acos(cos(radians(?))*cos(radians(lat))*cos(radians(lng)-radians(?))+sin(radians(?))*sin(radians(lat)))) AS distance",[$lat,$lng,$lat])->having('distance','<',$r); }
    public function user() { return $this->belongsTo(User::class); }
    public function members() { return $this->hasMany(ClubMember::class); }
    public function boards() { return $this->hasMany(ClubBoard::class)->orderBy('sort_order'); }
    public function posts() { return $this->hasMany(ClubPost::class); }
}
