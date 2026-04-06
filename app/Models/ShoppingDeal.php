<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ShoppingDeal extends Model
{
    protected $fillable = ['store_id','title','description','image_url','original_price','sale_price','discount_percent','url','is_active','expires_at'];
    protected $casts = ['is_active'=>'boolean','expires_at'=>'datetime','original_price'=>'decimal:2','sale_price'=>'decimal:2'];
    public function store() { return $this->belongsTo(ShoppingStore::class, 'store_id'); }
}
