<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Facades\Crypt;

/**
 * 외부 서비스 키를 DB에 암호화해서 저장한다(APP_KEY 기준). 저장값은 'enc:v1:' 로 시작.
 * 아직 암호화 전인 예전 값(접두어 없음)은 그대로 읽히므로, 배포 순서가 어긋나도 서비스가 끊기지 않는다.
 */
class Secret implements CastsAttributes
{
    public const PREFIX = 'enc:v1:';

    public static function reveal($value): ?string
    {
        if ($value === null || $value === '') return $value === null ? null : '';
        $value = (string) $value;
        if (!str_starts_with($value, self::PREFIX)) return $value;
        try {
            return Crypt::decryptString(substr($value, strlen(self::PREFIX)));
        } catch (\Throwable $e) {
            return null; // APP_KEY 가 바뀐 경우 — 잘못된 값을 쓰는 것보다 "없음"이 안전
        }
    }

    public static function seal($value): ?string
    {
        if ($value === null || $value === '') return $value;
        $value = (string) $value;
        return str_starts_with($value, self::PREFIX) ? $value : self::PREFIX . Crypt::encryptString($value);
    }

    public function get($model, string $key, $value, array $attributes)
    {
        return self::reveal($value);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        return [$key => self::seal($value)];
    }
}
