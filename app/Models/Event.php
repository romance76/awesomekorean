<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Event extends Model
{
    protected $fillable = ['title','description','content','image_url','category','organizer','venue','address','city','state','zipcode','lat','lng','start_date','end_date','price','url','source','source_id','view_count','attendee_count'];
    protected $casts = ['start_date'=>'datetime','end_date'=>'datetime','lat'=>'decimal:7','lng'=>'decimal:7'];
    public function attendees() { return $this->hasMany(EventAttendee::class); }
    public function scopeUpcoming($q) { return $q->where('start_date', '>=', now()); }
    public function scopeNearby($q,$lat,$lng,$r=50) { return $q->selectRaw("*, (3959*acos(cos(radians(?))*cos(radians(lat))*cos(radians(lng)-radians(?))+sin(radians(?))*sin(radians(lat)))) AS distance",[$lat,$lng,$lat])->having('distance','<',$r)->orderBy('distance'); }
}
