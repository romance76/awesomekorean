<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class QaAnswer extends Model
{
    protected $fillable = ['qa_post_id','user_id','content','like_count','is_best'];
    protected $casts = ['is_best'=>'boolean',];
}
