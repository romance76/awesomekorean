<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChatRoomUser extends Model
{
    protected $fillable = ['chat_room_id','user_id','last_read_at'];
    protected $casts = ['last_read_at'=>'datetime',];
}
