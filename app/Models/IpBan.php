<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class IpBan extends Model
{
    protected $fillable = ['ip_address','reason','banned_by','expires_at'];
    protected $casts = ['expires_at'=>'datetime',];
}
