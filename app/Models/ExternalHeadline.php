<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalHeadline extends Model
{
    protected $fillable = ['source', 'source_slug', 'title', 'summary', 'source_url', 'image_url', 'published_at'];
    protected $casts = ['published_at' => 'datetime'];
}
