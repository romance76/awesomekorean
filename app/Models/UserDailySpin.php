<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserDailySpin extends Model
{
    protected $fillable = ['user_id','spun_at','spun_date','points_won'];
    protected $casts = ['spun_at'=>'datetime','spun_date'=>'date'];
}
