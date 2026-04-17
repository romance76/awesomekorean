<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChatRoom extends Model
{
    protected $fillable = ['name','type','created_by'];

    public function users() {
        return $this->hasMany(ChatRoomUser::class);
    }

    // 방 참여자 (User 객체 직접)
    public function participants() {
        return $this->belongsToMany(User::class, 'chat_room_users', 'chat_room_id', 'user_id');
    }

    public function messages() {
        return $this->hasMany(ChatMessage::class);
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }
}
