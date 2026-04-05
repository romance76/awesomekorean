<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $fillable = ['commentable_type','commentable_id','user_id','parent_id','content','like_count','is_hidden'];
    protected $casts = ['is_hidden'=>'boolean'];
    public function user() { return $this->belongsTo(User::class); }
    public function commentable() { return $this->morphTo(); }
    public function replies() { return $this->hasMany(Comment::class, 'parent_id'); }
    public function parent() { return $this->belongsTo(Comment::class, 'parent_id'); }
}
