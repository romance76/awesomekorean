<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ElderSosLog extends Model
{
    protected $fillable = ['user_id','lat','lng','message','contacts_notified','resolved_at'];
    protected $casts = ['contacts_notified'=>'array','resolved_at'=>'datetime',];
}
