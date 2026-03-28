<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GamePlayer extends Model
{
    protected $fillable = ['game_room_id','user_id','seat','is_ready','points_result'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function room()
    {
        return $this->belongsTo(GameRoom::class, 'game_room_id');
    }
}
