<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 인보이스 메일 발송 이력 한 줄 (자동 발송 / 관리자 재발송). triggered_by = 'auto' 또는 관리자 회원 번호. */
class InvoiceDelivery extends Model
{
    public const QUEUED = 'queued';
    public const SENT = 'sent';
    public const FAILED = 'failed';

    protected $fillable = ['invoice_id', 'to_email', 'status', 'error', 'sent_at', 'triggered_by'];

    protected $casts = ['sent_at' => 'datetime'];

    public function invoice() { return $this->belongsTo(Invoice::class); }
}
