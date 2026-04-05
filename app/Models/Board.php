<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Board extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function posts() { return $this->hasMany(Post::class); }
}
