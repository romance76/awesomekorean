<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShoppingDeal extends Model
{
    protected $fillable = [
        'store_id','title','description','image_url',
        'original_price','sale_price','category','unit',
        'source_url','valid_from','valid_until',
        'view_count','is_featured','is_active',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_active'   => 'boolean',
        'valid_from'  => 'date',
        'valid_until' => 'date',
    ];

    public function store() { return $this->belongsTo(ShoppingStore::class, 'store_id'); }

    public function getDiscountRateAttribute(): ?int
    {
        if ($this->original_price && $this->sale_price && $this->original_price > 0) {
            return (int) round((1 - $this->sale_price / $this->original_price) * 100);
        }
        return null;
    }
}
