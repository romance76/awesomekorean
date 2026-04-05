<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class News extends Model
{
    protected $fillable = ['title','content','summary','source','source_url','image_url','category_id','subcategory','view_count','published_at'];
    protected $casts = ['published_at'=>'datetime',];
}
