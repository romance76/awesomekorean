<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// 사이트 공식 SNS 계정(인스타그램·스레드). 비밀값(app_secret, access_token)은 APP_KEY 로 암호화 저장.
class SocialAccount extends Model
{
    public const PLATFORMS = ['instagram', 'threads'];

    protected $fillable = [
        'platform', 'handle', 'profile_url', 'login_email', 'account_id', 'page_id', 'app_id',
        'app_secret', 'access_token', 'token_expires_at', 'notes', 'is_active', 'autopost_enabled', 'updated_by',
    ];

    protected $hidden = ['app_secret', 'access_token'];

    protected $casts = [
        'app_secret' => 'encrypted',
        'access_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'autopost_enabled' => 'boolean',
    ];
}
