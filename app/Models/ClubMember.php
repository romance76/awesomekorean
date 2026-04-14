<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ClubMember extends Model
{
    protected $fillable = ['club_id','user_id','role','grade','status','joined_at'];
    protected $casts = ['joined_at'=>'datetime'];
    public function club() { return $this->belongsTo(Club::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function scopeApproved($q) { return $q->where('status', 'approved'); }
    public function scopePending($q) { return $q->where('status', 'pending'); }
}
