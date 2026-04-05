<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PointLog extends Model
{
    protected $fillable = ['user_id','amount','type','reason','related_type','related_id','balance_after'];

}
