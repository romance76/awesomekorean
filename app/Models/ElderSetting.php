<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElderSetting extends Model
{
    protected $fillable = [
        'user_id', 'elder_mode', 'guardian_phone', 'guardian_name',
        'checkin_interval', 'last_checkin_at', 'last_sos_at', 'alert_sent',
    ];

    protected $casts = [
        'elder_mode'      => 'boolean',
        'alert_sent'      => 'boolean',
        'last_checkin_at' => 'datetime',
        'last_sos_at'     => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
