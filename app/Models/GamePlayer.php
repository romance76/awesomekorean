<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GamePlayer extends Model
{
    protected $fillable = ['game_room_id','user_id','score','is_winner','bet_amount'];
    protected $casts = ['is_winner'=>'boolean',];
}
