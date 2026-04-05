<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GameRoom extends Model
{
    protected $fillable = ['game_type','host_id','status','max_players','bet_amount','settings'];
    protected $casts = ['settings'=>'array',];
}
