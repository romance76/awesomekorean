<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserPlaylistTrack extends Model
{
    protected $fillable = ['playlist_id','track_id','sort_order'];

}
