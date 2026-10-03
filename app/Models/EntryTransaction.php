<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntryTransaction extends Model
{
    protected $fillable = [
        'user_id', 'amount', 'balance_before', 'balance_after',
        'transaction_type', 'source', 'reference_type', 'reference_id',
        'description', 'idempotency_key',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
