<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShoppingStore extends Model
{
    protected $fillable = ['name','type','region','website','rss_url','logo','description','is_active'];
    protected $casts    = ['is_active' => 'boolean'];

    public function deals() { return $this->hasMany(ShoppingDeal::class, 'store_id'); }
}
