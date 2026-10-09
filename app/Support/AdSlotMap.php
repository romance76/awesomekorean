<?php

namespace App\Support;

/**
 * 광고 자리(페이지 × 위치) 단일 기준표 — 프론트 resources/js/config/adSlotMap.js 와 동일하게 유지.
 * 실제 화면에 <AdSlot page=".." position=".."> 가 있는 조합만 신규 광고 신청을 받는다.
 * (기존에 이미 예약된 광고 데이터는 건드리지 않는다.)
 */
class AdSlotMap
{
    public const MAP = [
        'home'       => ['left', 'right'],
        'community'  => ['left', 'right'],
        'qa'         => ['left', 'right'],
        'news'       => ['left', 'right'],
        'recipes'    => ['left', 'right'],
        'groupbuy'   => ['left', 'right'],
        'market'     => ['left', 'right'],
        'jobs'       => ['left', 'right'],
        'realestate' => ['left', 'right'],
        'directory'  => ['left', 'right'],
        'clubs'      => ['left', 'right'],
        'events'     => ['left'],   // 이벤트 페이지는 오른쪽 열 없음
    ];

    public static function positionsOf(string $page): array
    {
        return self::MAP[$page] ?? [];
    }

    public static function exists(string $page, string $position): bool
    {
        return in_array($position, self::positionsOf($page), true);
    }
}
