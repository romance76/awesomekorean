<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EventAttendee extends Model
{
    protected $fillable = ['event_id','user_id','status','reminder_sent_at','proof_file','proof_status','reviewed_at'];
    protected $casts = ['reminder_sent_at'=>'datetime','reviewed_at'=>'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function event() { return $this->belongsTo(Event::class); }
}
