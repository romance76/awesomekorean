<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BusinessReview extends Model
{
    protected $fillable = ['business_id','user_id','rating','content','images'];
    protected $casts = ['images'=>'array'];
    public function business() { return $this->belongsTo(Business::class); }
    public function user() { return $this->belongsTo(User::class); }
}
