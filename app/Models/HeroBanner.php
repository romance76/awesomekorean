<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class HeroBanner extends Model
{
    protected $fillable = ['title','subtitle','image_url','image_url_en','image_only','bg_color','text_color','link_type','link_url','event_id','link_page','is_active','sort_order'];
    protected $casts = ['is_active' => 'boolean', 'image_only' => 'boolean'];
    public function event() { return $this->belongsTo(Event::class); }
    public function scopeActive($q) { return $q->where('is_active', true); }
}
