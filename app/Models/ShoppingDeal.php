<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ShoppingDeal extends Model
{
    protected $fillable = ['store_id','title','description','image_url','original_price','sale_price','discount_percent','url','is_active','expires_at'];
    protected $casts = ['is_active'=>'boolean','expires_at'=>'datetime',];
}
