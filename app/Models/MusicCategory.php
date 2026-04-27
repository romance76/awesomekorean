<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MusicCategory extends Model
{
    protected $fillable = ['name','slug','image','sort_order','korean_queries','pop_queries','auto_fetch','is_active'];
    protected $casts = ['auto_fetch' => 'boolean', 'is_active' => 'boolean'];

}
