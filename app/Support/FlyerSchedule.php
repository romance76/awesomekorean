<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * NEW 전단 광고의 시간/가격 규칙.
 *  - 슬롯의 날짜·시간은 그 지역의 "현지 시각" 기준 (전국 = 미 동부시간 ET).
 *  - 가격: 지역 기본가 × 시간대 배율(피크 17~22시, 심야 0~5시) — 모두 point_settings 로 조정.
 */
class FlyerSchedule
{
    public const NATIONAL = 'ALL';
    public const NATIONAL_TZ = 'America/New_York';

    /** 주 코드 → IANA 시간대 (여러 시간대에 걸친 주는 인구가 많은 쪽) */
    private const STATE_TZ = [
        'CT' => 'America/New_York', 'DC' => 'America/New_York', 'DE' => 'America/New_York', 'FL' => 'America/New_York',
        'GA' => 'America/New_York', 'IN' => 'America/New_York', 'KY' => 'America/New_York', 'MA' => 'America/New_York',
        'MD' => 'America/New_York', 'ME' => 'America/New_York', 'MI' => 'America/New_York', 'NC' => 'America/New_York',
        'NH' => 'America/New_York', 'NJ' => 'America/New_York', 'NY' => 'America/New_York', 'OH' => 'America/New_York',
        'PA' => 'America/New_York', 'RI' => 'America/New_York', 'SC' => 'America/New_York', 'VA' => 'America/New_York',
        'VT' => 'America/New_York', 'WV' => 'America/New_York',
        'AL' => 'America/Chicago', 'AR' => 'America/Chicago', 'IA' => 'America/Chicago', 'IL' => 'America/Chicago',
        'KS' => 'America/Chicago', 'LA' => 'America/Chicago', 'MN' => 'America/Chicago', 'MO' => 'America/Chicago',
        'MS' => 'America/Chicago', 'ND' => 'America/Chicago', 'NE' => 'America/Chicago', 'OK' => 'America/Chicago',
        'SD' => 'America/Chicago', 'TN' => 'America/Chicago', 'TX' => 'America/Chicago', 'WI' => 'America/Chicago',
        'CO' => 'America/Denver', 'ID' => 'America/Denver', 'MT' => 'America/Denver', 'NM' => 'America/Denver',
        'UT' => 'America/Denver', 'WY' => 'America/Denver', 'AZ' => 'America/Phoenix',
        'CA' => 'America/Los_Angeles', 'NV' => 'America/Los_Angeles', 'OR' => 'America/Los_Angeles', 'WA' => 'America/Los_Angeles',
        'AK' => 'America/Anchorage', 'HI' => 'Pacific/Honolulu',
    ];

    public static function isState(?string $code): bool
    {
        return $code !== null && isset(self::STATE_TZ[strtoupper($code)]);
    }

    public static function states(): array
    {
        return array_keys(self::STATE_TZ);
    }

    public static function regionKey(string $scope, ?string $state): string
    {
        return $scope === 'state' ? strtoupper((string) $state) : self::NATIONAL;
    }

    public static function timezone(string $regionKey): string
    {
        return self::STATE_TZ[$regionKey] ?? self::NATIONAL_TZ;
    }

    /** 그 지역의 현재 현지 시각 */
    public static function now(string $regionKey): Carbon
    {
        return Carbon::now(self::timezone($regionKey));
    }

    /** 시간(0~23)의 가격 (포인트). $regionKey 는 'ALL' 또는 주 코드 */
    public static function hourPrice(string $regionKey, int $hour): int
    {
        $base = $regionKey === self::NATIONAL
            ? PointRules::get('flyer_price_national', 60)
            : PointRules::get('flyer_price_state', 30);

        $pct = 100;
        if ($hour >= 17 && $hour <= 22) $pct = PointRules::get('flyer_peak_pct', 150);
        elseif ($hour >= 0 && $hour <= 5) $pct = PointRules::get('flyer_night_pct', 50);

        return (int) round($base * $pct / 100);
    }

    /** 24시간 가격표 [hour => price] */
    public static function priceTable(string $regionKey): array
    {
        $t = [];
        for ($h = 0; $h < 24; $h++) $t[$h] = self::hourPrice($regionKey, $h);
        return $t;
    }

    public static function maxDays(): int
    {
        return max(1, PointRules::get('flyer_max_days', 30));
    }

    public static function windowDays(): int
    {
        return max(1, PointRules::get('flyer_window_days', 30));
    }
}
