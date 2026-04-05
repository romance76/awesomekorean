<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MusicCategory extends Model
{
    protected $fillable = ['name','slug','image','sort_order'];

}
