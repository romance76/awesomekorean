<?php

namespace App\Support;

/**
 * 미국 주 인접 관계 — 공동구매 "기본(지역)" 범위 = 작성자가 속한 주 + 육지로 맞닿은 주.
 * 데이터는 database/data/us_state_adjacency.json (화면 resources/js/utils/usStates.js 도 같은 파일을 읽는다).
 * 알래스카·하와이는 육지로 맞닿은 주가 없어 자기 주만 포함한다. 네 모서리만 닿는 주(AZ-CO, NM-UT)는 인접으로 보지 않는다.
 */
class UsStates
{
    private static ?array $map = null;

    public static function map(): array
    {
        if (self::$map === null) {
            $raw = @file_get_contents(base_path('database/data/us_state_adjacency.json'));
            self::$map = $raw ? (json_decode($raw, true) ?: []) : [];
        }
        return self::$map;
    }

    /** 테스트용: 파일 대신 지도를 직접 넣는다 */
    public static function useMap(?array $map): void
    {
        self::$map = $map;
    }

    public static function normalize($state): string
    {
        return strtoupper(trim((string) $state));
    }

    public static function isState($state): bool
    {
        return array_key_exists(self::normalize($state), self::map());
    }

    /** 작성자 주 + 인접 주 (자기 주가 맨 앞). 모르는 주면 빈 배열 */
    public static function region($state): array
    {
        $s = self::normalize($state);
        $map = self::map();
        if (!isset($map[$s])) return [];
        return array_values(array_unique(array_merge([$s], $map[$s])));
    }
}
