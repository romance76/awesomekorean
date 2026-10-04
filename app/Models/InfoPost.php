<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfoPost extends Model
{
    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'meta_title', 'meta_description',
        'keyword_term', 'cover_image_url', 'category', 'view_count',
        'is_published', 'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public const CATEGORIES = [
        '생활정보', '이민·비자', '세금', '금융', '보험', '부동산',
        '교통', '교육', '날씨·안전', '통신', '창업·비즈니스',
    ];

    public function scopePublished($q)
    {
        return $q->where('is_published', true)->whereNotNull('published_at');
    }
}
