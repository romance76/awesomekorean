<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = ['board_id','user_id','title','content','category','images','view_count','like_count','comment_count','is_pinned','is_hidden','lat','lng','city','state','zipcode'];
    protected $casts = ['images'=>'array','is_pinned'=>'boolean','is_hidden'=>'boolean','lat'=>'decimal:7','lng'=>'decimal:7'];
    public function user() { return $this->belongsTo(User::class); }
    public function board() { return $this->belongsTo(Board::class); }
    public function comments() { return $this->morphMany(Comment::class, 'commentable'); }
    public function likes() { return $this->hasMany(PostLike::class); }
    public function scopeNearby($q,$lat,$lng,$r=50) { return $q->selectRaw("*, (3959*acos(cos(radians(?))*cos(radians(lat))*cos(radians(lng)-radians(?))+sin(radians(?))*sin(radians(lat)))) AS distance",[$lat,$lng,$lat])->having('distance','<',$r)->orderBy('distance'); }
    public function scopeVisible($q) { return $q->where('is_hidden', false); }
}
