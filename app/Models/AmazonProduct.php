<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonProduct extends Model
{
    protected $fillable = [
        'asin', 'amazon_url', 'affiliate_url', 'title', 'image_url', 'category',
        'price', 'amazon_image_urls', 'own_image_urls',
        'our_description', 'display_order', 'is_featured', 'is_active',
    ];

    protected $casts = [
        'is_featured'       => 'boolean',
        'is_active'         => 'boolean',
        'price'             => 'decimal:2',
        'amazon_image_urls' => 'array',
        'own_image_urls'    => 'array',
    ];
}
