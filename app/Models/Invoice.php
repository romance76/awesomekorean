<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 결제 인보이스(영수증). 금액은 센트(정수), 이름·이메일·상품은 발행 시점 값 그대로(스냅샷).
 * 만드는 곳은 App\Support\Invoicing 한 곳뿐이다.
 */
class Invoice extends Model
{
    public const STATUS_ISSUED = 'issued';
    public const STATUS_VOID = 'void';

    protected $fillable = [
        'invoice_no', 'payment_id', 'user_id', 'status', 'kind', 'currency',
        'subtotal_cents', 'discount_cents', 'total_cents', 'line_items',
        'billing_name', 'billing_email', 'issued_at', 'voided_at', 'void_reason',
    ];

    protected $casts = [
        'line_items' => 'array',
        'subtotal_cents' => 'integer',
        'discount_cents' => 'integer',
        'total_cents' => 'integer',
        'issued_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function payment() { return $this->belongsTo(Payment::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function deliveries() { return $this->hasMany(InvoiceDelivery::class)->orderByDesc('id'); }
    public function latestDelivery() { return $this->hasOne(InvoiceDelivery::class)->latestOfMany(); }
}
