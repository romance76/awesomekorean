<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class News extends Model
{
    protected $fillable = ['title','title_en','content','content_en','summary','source','source_url','is_external','image_url','local_image','category_id','subcategory','view_count','published_at','is_active'];
    protected $casts = ['published_at'=>'datetime','is_active'=>'boolean','is_external'=>'boolean'];
    public function category() { return $this->belongsTo(NewsCategory::class, 'category_id'); }
}
