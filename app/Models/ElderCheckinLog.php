<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ElderCheckinLog extends Model
{
    protected $fillable = ['user_id','checked_in_at','lat','lng','status'];
    protected $casts = ['checked_in_at'=>'datetime',];
}
