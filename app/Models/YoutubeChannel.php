<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YoutubeChannel extends Model
{
    public const CATEGORIES = [
        'life' => '생활·이민', 'food' => '먹방·맛집', 'family' => '육아·교육', 'money' => '재테크·세금', 'realestate' => '부동산', 'travel' => '여행',
        'beauty' => '뷰티·패션', 'vlog' => '일상 vlog', 'etc' => '기타',
    ];

    protected $fillable = ['user_id', 'channel_id', 'handle', 'title', 'description', 'intro', 'thumbnail_url', 'banner_url', 'category', 'subscriber_count',
        'video_count', 'status', 'reject_reason', 'is_featured', 'featured_until', 'latest_video_at', 'last_synced_at', 'view_count'];

    protected $casts = ['is_featured' => 'boolean', 'featured_until' => 'datetime', 'latest_video_at' => 'datetime', 'last_synced_at' => 'datetime'];

    public function videos()
    {
        return $this->hasMany(YoutubeVideo::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
