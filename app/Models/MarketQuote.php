<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketQuote extends Model
{
    protected $fillable = ['symbol', 'name', 'category', 'price', 'change_pct', 'change', 'volume', 'sparkline', 'sort_order', 'quoted_at'];
    protected $casts = ['sparkline' => 'array', 'quoted_at' => 'datetime'];
}
