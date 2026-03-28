<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShortLike extends Model
{
    protected $fillable = ['user_id', 'short_id'];

    public function user()  { return $this->belongsTo(User::class); }
    public function short() { return $this->belongsTo(Short::class); }
}
