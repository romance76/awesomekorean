<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Club extends Model
{
    protected $fillable = ['user_id','name','description','category','image','type','zipcode','member_count','is_active'];
    protected $casts = ['is_active'=>'boolean'];
    public function user() { return $this->belongsTo(User::class); }
    public function members() { return $this->hasMany(ClubMember::class); }
    public function posts() { return $this->hasMany(ClubPost::class); }
}
