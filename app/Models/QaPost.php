<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class QaPost extends Model
{
    protected $fillable = ['user_id','category_id','title','content','bounty_points','view_count','answer_count','is_resolved','best_answer_id','is_hidden'];
    protected $casts = ['is_resolved'=>'boolean','is_hidden'=>'boolean'];
    public function user() { return $this->belongsTo(User::class); }
    public function category() { return $this->belongsTo(QaCategory::class, 'category_id'); }
    public function answers() { return $this->hasMany(QaAnswer::class); }
    public function bestAnswer() { return $this->belongsTo(QaAnswer::class, 'best_answer_id'); }
}
