<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EarningsEvent extends Model
{
    protected $fillable = ['report_date', 'symbol', 'name', 'time_slot', 'market_cap', 'eps_forecast', 'last_year_eps', 'fiscal_quarter', 'est_count'];
    protected $casts = ['report_date' => 'date:Y-m-d'];
}
