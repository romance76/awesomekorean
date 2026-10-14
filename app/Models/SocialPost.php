<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// SNS 자동 게시 대기열 한 줄 (인스타그램·스레드). 상태: pending → approved → posted / failed / skipped
class SocialPost extends Model
{
    public const PLATFORMS = ['instagram', 'threads'];
    public const STATUSES = ['pending', 'approved', 'posted', 'failed', 'skipped'];

    protected $fillable = [
        'source_type', 'source_id', 'platform', 'title', 'caption', 'link_url', 'image_url', 'video_url', 'status', 'priority',
        'not_before', 'posted_at', 'external_id', 'external_url', 'error', 'attempts', 'approved_by', 'created_by',
    ];

    protected $casts = ['not_before' => 'datetime', 'posted_at' => 'datetime'];
}
