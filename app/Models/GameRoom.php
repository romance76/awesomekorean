<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameRoom extends Model
{
    protected $fillable = ['code','type','status','min_players','max_players','bet_points','state','created_by'];
    protected $casts = ['state' => 'array'];

    public function players()
    {
        return $this->hasMany(GamePlayer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
