<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfoKeyword extends Model
{
    protected $fillable = ['term', 'source', 'search_volume', 'competition', 'status', 'notes'];
}
