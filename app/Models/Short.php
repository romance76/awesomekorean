<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Short extends Model
{
    protected $fillable = [
        'user_id','url','embed_url','platform','title',
        'description','thumbnail','tags','view_count',
        'like_count','share_count','is_active',
    ];

    protected $casts = ['tags' => 'array', 'is_active' => 'boolean'];

    public function user()    { return $this->belongsTo(User::class); }
    public function likes()   { return $this->hasMany(ShortLike::class); }
}
