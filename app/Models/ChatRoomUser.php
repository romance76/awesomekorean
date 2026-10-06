<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChatRoomUser extends Model
{
    protected $fillable = ['chat_room_id','user_id','last_read_at','left_at','access_expires_at'];
    protected $casts = ['last_read_at'=>'datetime','left_at'=>'datetime','access_expires_at'=>'datetime'];
}
