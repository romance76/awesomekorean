<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonProduct extends Model
{
    protected $fillable = [
        'asin', 'amazon_url', 'affiliate_url', 'title', 'image_url', 'category',
        'price', 'amazon_image_urls', 'own_image_urls',
        'our_description', 'display_order', 'is_featured', 'is_active',
        'user_id', 'status', 'rating', 'affiliate_tag', 'admin_note', 'published_at',
    ];

    protected $casts = [
        'is_featured'       => 'boolean',
        'is_active'         => 'boolean',
        'price'             => 'decimal:2',
        'amazon_image_urls' => 'array',
        'own_image_urls'    => 'array',
        'published_at'      => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }

    /** 회원이 쓴 내돈내산 리뷰인지 (관리자가 올린 상품은 user_id = NULL) */
    public function isMemberReview(): bool { return $this->user_id !== null; }
}
