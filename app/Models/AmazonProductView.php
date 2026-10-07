<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonProductView extends Model
{
    public $timestamps = false;
    protected $fillable = ['amazon_product_id', 'user_id', 'viewed_at'];
    protected $casts = ['viewed_at' => 'datetime'];
}
