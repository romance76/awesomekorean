<?php

namespace App\Support;

use App\Models\ApiKey;
use Illuminate\Support\Facades\Cache;

/**
 * 구글 애널리틱스(GA4) 측정 ID — 관리자 "API 키 관리"에서 service=google_analytics 로 등록한 값.
 * 비활성화하거나 지우면 추적 코드가 사이트에서 빠진다. 페이지마다 DB를 치지 않도록 짧게 캐시.
 */
class Analytics
{
    public const SERVICE = 'google_analytics';
    public const CACHE_KEY = 'analytics_measurement_id';

    /** GA4 측정 ID 형식 (예: G-ABC123DEF4) */
    public static function isValid(?string $id): bool
    {
        return (bool) preg_match('/^G-[A-Z0-9]{6,12}$/', (string) $id);
    }

    public static function measurementId(): ?string
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            $id = trim((string) ApiKey::keyFor(self::SERVICE));
            return self::isValid($id) ? $id : '';
        }) ?: null;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
