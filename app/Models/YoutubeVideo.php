<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YoutubeVideo extends Model
{
    protected $fillable = ['youtube_channel_id', 'video_id', 'title', 'thumbnail_url', 'duration_seconds', 'is_short', 'view_count', 'published_at'];

    protected $casts = ['is_short' => 'boolean', 'published_at' => 'datetime'];

    public function channel()
    {
        return $this->belongsTo(YoutubeChannel::class, 'youtube_channel_id');
    }
}
