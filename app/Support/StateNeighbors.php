<?php

namespace App\Support;

/**
 * 미국 50개 주 + DC 의 인접 주 맵.
 *
 * 사용 목적:
 *  - 구인구직 state_plus 상위노출: 광고주가 지정한 `promotion_states` 안에
 *    사용자 현재 주 또는 인접 주가 포함되는지 빠르게 판정
 *  - 예) 조지아(GA) 아틀란타 지역 사용자는 GA/AL/FL/NC/SC/TN 의
 *    state_plus 광고를 상위에서 볼 수 있어야 함
 */
class StateNeighbors
{
    /** 자기 자신 포함. 약어는 항상 대문자 2글자. */
    public const MAP = [
        'AL' => ['AL','FL','GA','MS','TN'],
        'AK' => ['AK'],
        'AZ' => ['AZ','CA','CO','NM','NV','UT'],
        'AR' => ['AR','LA','MO','MS','OK','TN','TX'],
        'CA' => ['CA','AZ','NV','OR'],
        'CO' => ['CO','AZ','KS','NE','NM','OK','UT','WY'],
        'CT' => ['CT','MA','NY','RI'],
        'DE' => ['DE','MD','NJ','PA'],
        'FL' => ['FL','AL','GA'],
        'GA' => ['GA','AL','FL','NC','SC','TN'],
        'HI' => ['HI'],
        'ID' => ['ID','MT','NV','OR','UT','WA','WY'],
        'IL' => ['IL','IA','IN','KY','MO','WI'],
        'IN' => ['IN','IL','KY','MI','OH'],
        'IA' => ['IA','IL','MN','MO','NE','SD','WI'],
        'KS' => ['KS','CO','MO','NE','OK'],
        'KY' => ['KY','IL','IN','MO','OH','TN','VA','WV'],
        'LA' => ['LA','AR','MS','TX'],
        'ME' => ['ME','NH'],
        'MD' => ['MD','DE','PA','VA','WV','DC'],
        'MA' => ['MA','CT','NH','NY','RI','VT'],
        'MI' => ['MI','IN','OH','WI'],
        'MN' => ['MN','IA','ND','SD','WI'],
        'MS' => ['MS','AL','AR','LA','TN'],
        'MO' => ['MO','AR','IA','IL','KS','KY','NE','OK','TN'],
        'MT' => ['MT','ID','ND','SD','WY'],
        'NE' => ['NE','CO','IA','KS','MO','SD','WY'],
        'NV' => ['NV','AZ','CA','ID','OR','UT'],
        'NH' => ['NH','MA','ME','VT'],
        'NJ' => ['NJ','DE','NY','PA'],
        'NM' => ['NM','AZ','CO','OK','TX','UT'],
        'NY' => ['NY','CT','MA','NJ','PA','VT'],
        'NC' => ['NC','GA','SC','TN','VA'],
        'ND' => ['ND','MN','MT','SD'],
        'OH' => ['OH','IN','KY','MI','PA','WV'],
        'OK' => ['OK','AR','CO','KS','MO','NM','TX'],
        'OR' => ['OR','CA','ID','NV','WA'],
        'PA' => ['PA','DE','MD','NJ','NY','OH','WV'],
        'RI' => ['RI','CT','MA'],
        'SC' => ['SC','GA','NC'],
        'SD' => ['SD','IA','MN','MT','ND','NE','WY'],
        'TN' => ['TN','AL','AR','GA','KY','MO','MS','NC','VA'],
        'TX' => ['TX','AR','LA','NM','OK'],
        'UT' => ['UT','AZ','CO','ID','NM','NV','WY'],
        'VT' => ['VT','MA','NH','NY'],
        'VA' => ['VA','KY','MD','NC','TN','WV','DC'],
        'WA' => ['WA','ID','OR'],
        'WV' => ['WV','KY','MD','OH','PA','VA'],
        'WI' => ['WI','IA','IL','MI','MN'],
        'WY' => ['WY','CO','ID','MT','NE','SD','UT'],
        'DC' => ['DC','MD','VA'],
    ];

    /**
     * 주 약어로 자기 자신 + 인접 주 배열을 반환. 모르는 주는 자기 자신만.
     */
    public static function neighbors(?string $state): array
    {
        if (!$state) return [];
        $k = strtoupper(trim($state));
        return self::MAP[$k] ?? [$k];
    }

    /**
     * SQL IN 절에 안전하게 쓸 수 있는 문자열 — "'GA','AL','FL',…".
     * 값은 [A-Z]{2} 로만 제한되므로 이스케이프 불필요하지만 방어적으로 처리.
     */
    public static function sqlInList(?string $state): string
    {
        $states = self::neighbors($state);
        $clean = array_filter($states, fn($s) => preg_match('/^[A-Z]{2}$/', $s));
        if (!$clean) return "''";
        return "'" . implode("','", $clean) . "'";
    }
}
