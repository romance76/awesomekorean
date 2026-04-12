<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class News extends Model
{
    protected $fillable = ['title','title_en','content','content_en','summary','source','source_url','image_url','category_id','subcategory','view_count','published_at'];
    protected $casts = ['published_at'=>'datetime'];
    public function category() { return $this->belongsTo(NewsCategory::class, 'category_id'); }
}
