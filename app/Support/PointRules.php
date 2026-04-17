<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * P2B-2: 포인트 규칙 DB 조회 공통 헬퍼.
 * point_settings 테이블에서 값을 Cache 5분 TTL 로 조회.
 * 관리자가 /admin/point-settings 에서 변경하면 다음 5분 내 반영됨.
 */
class PointRules
{
    public static function get(string $key, int $default = 0): int
    {
        $all = static::all();
        return (int) ($all[$key] ?? $default);
    }

    public static function raw(string $key, string $default = ''): string
    {
        $all = static::all();
        return (string) ($all[$key] ?? $default);
    }

    public static function all(): array
    {
        return Cache::remember('point_rules_kv', 300, function () {
            return DB::table('point_settings')->pluck('value', 'key')->toArray();
        });
    }

    public static function flush(): void
    {
        Cache::forget('point_rules_kv');
    }
}
