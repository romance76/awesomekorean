<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonProduct extends Model
{
    protected $fillable = [
        'asin', 'amazon_url', 'affiliate_url', 'title', 'image_url', 'category',
        'our_description', 'display_order', 'is_featured', 'is_active',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_active'   => 'boolean',
    ];
}
