<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Event extends Model
{
    protected $fillable = ['title','description','content','image_url','category','organizer','venue','address','city','state','zipcode','lat','lng','start_date','end_date','price','url','source','source_id','view_count','attendee_count'];
    protected $casts = ['start_date'=>'datetime','end_date'=>'datetime',];
}
