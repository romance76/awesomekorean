<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MusicTrack extends Model
{
    protected $fillable = ['category_id','title','artist','youtube_url','youtube_id','duration','sort_order','is_user_submitted'];
    protected $casts = ['is_user_submitted' => 'boolean'];
}
