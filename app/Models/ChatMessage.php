<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChatMessage extends Model
{
    protected $fillable = ['chat_room_id','user_id','content','type','file_url','is_read'];
    protected $casts = ['is_read'=>'boolean',];
}
