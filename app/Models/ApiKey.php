<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $fillable = ['name', 'service', 'api_key', 'description', 'is_active'];

    protected $hidden = ['api_key']; // JSON 직렬화 시 키 노출 방지

    // DB 에는 암호화된 값이 저장되고, 읽을 때 자동으로 풀린다
    protected $casts = ['api_key' => \App\Casts\Secret::class];

    protected $appends = ['masked_key'];

    public function getMaskedKeyAttribute(): string
    {
        $key = $this->api_key ?? '';
        if (strlen($key) <= 8) return '****';
        return substr($key, 0, 4) . '****' . substr($key, -4);
    }

    /**
     * 서비스 코드로 활성 API 키 조회
     */
    public static function keyFor(string $service): ?string
    {
        return static::where('service', $service)->where('is_active', true)->value('api_key');
    }
}
