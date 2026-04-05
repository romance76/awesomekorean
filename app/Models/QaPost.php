<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class QaPost extends Model
{
    protected $fillable = ['user_id','category_id','title','content','bounty_points','view_count','answer_count','is_resolved','best_answer_id'];
    protected $casts = ['is_resolved'=>'boolean',];
}
