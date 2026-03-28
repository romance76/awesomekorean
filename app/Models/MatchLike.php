<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchLike extends Model
{
    protected $fillable = ['user_id','liked_user_id','is_match'];

    protected $casts = ['is_match' => 'boolean'];

    public function user()      { return $this->belongsTo(User::class, 'user_id'); }
    public function likedUser() { return $this->belongsTo(User::class, 'liked_user_id'); }
}
