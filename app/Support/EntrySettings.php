<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Entry 설정 DB 조회 공통 헬퍼. PointRules와 동일한 패턴이지만
 * entry_settings 테이블을 따로 읽는다 — Point 설정과 캐시 키까지 분리해
 * 두 시스템이 코드 레벨에서도 섞이지 않게 한다.
 */
class EntrySettings
{
    public static function get(string $key, int $default = 0): int
    {
        $all = static::all();
        return (int) ($all[$key] ?? $default);
    }

    public static function all(): array
    {
        return Cache::remember('entry_settings_kv', 300, function () {
            return DB::table('entry_settings')->pluck('value', 'key')->toArray();
        });
    }

    public static function flush(): void
    {
        Cache::forget('entry_settings_kv');
    }
}
