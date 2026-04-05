<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RecipeCategory extends Model
{
    protected $fillable = ['name','slug','sort_order'];

}
