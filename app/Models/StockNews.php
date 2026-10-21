<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockNews extends Model
{
    protected $table = 'stock_news';

    protected $fillable = ['source', 'title', 'summary', 'url', 'url_hash', 'image_url', 'lang', 'published_at'];

    protected $casts = ['published_at' => 'datetime'];
}
