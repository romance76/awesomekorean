<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Event extends Model
{
    protected $fillable = ['user_id','title','description','content','image_url','banner_image','banner_subtitle','banner_color','category','event_type','is_pinned','organizer','venue','address','city','state','zipcode','lat','lng','start_date','end_date','price','reward_points','is_free','url','event_url','source','source_id','view_count','attendee_count','max_attendees','is_active'];
    protected $casts = ['start_date'=>'datetime','end_date'=>'datetime','lat'=>'decimal:7','lng'=>'decimal:7','is_active'=>'boolean','is_free'=>'boolean','is_pinned'=>'boolean'];
    public function user() { return $this->belongsTo(User::class); }
    public function attendees() { return $this->hasMany(EventAttendee::class); }
    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeUpcoming($q) { return $q->where('start_date', '>=', now()); }
    public function scopeNearby($q,$lat,$lng,$r=50) { return $q->selectRaw("*, (3959*acos(cos(radians(?))*cos(radians(lat))*cos(radians(lng)-radians(?))+sin(radians(?))*sin(radians(lat)))) AS distance",[$lat,$lng,$lat])->having('distance','<',$r); }
}
