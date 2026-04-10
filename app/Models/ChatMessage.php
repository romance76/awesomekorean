<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChatMessage extends Model
{
    protected $fillable = ['chat_room_id','user_id','content','type','file_url','is_read','pinned_until'];
    protected $casts = ['is_read'=>'boolean','pinned_until'=>'datetime'];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function room() {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }
}
