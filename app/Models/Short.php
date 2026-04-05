<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Short extends Model
{
    protected $fillable = ['user_id','title','video_url','thumbnail_url','youtube_id','duration','view_count','like_count','comment_count','is_active'];
    protected $casts = ['is_active'=>'boolean',];
}
