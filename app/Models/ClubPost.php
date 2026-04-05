<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ClubPost extends Model
{
    protected $fillable = ['club_id','user_id','title','content','images','like_count','comment_count'];
    protected $casts = ['images'=>'array',];
}
