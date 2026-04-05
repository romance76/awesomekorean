<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RecipePost extends Model
{
    protected $fillable = ['user_id','title','title_ko','content','content_ko','ingredients','ingredients_ko','steps','steps_ko','category_id','images','servings','prep_time','cook_time','difficulty','view_count','like_count','comment_count'];
    protected $casts = ['images'=>'array','ingredients'=>'array','ingredients_ko'=>'array','steps'=>'array','steps_ko'=>'array',];
}
