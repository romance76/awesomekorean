<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketQuote extends Model
{
    protected $fillable = ['symbol', 'name', 'category', 'price', 'change_pct', 'change', 'volume', 'sparkline', 'sort_order'];
    protected $casts = ['sparkline' => 'array'];
}
