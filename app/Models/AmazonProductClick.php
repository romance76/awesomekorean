<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonProductClick extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'amazon_product_id', 'asin', 'category', 'user_id', 'page', 'clicked_at',
    ];
}
