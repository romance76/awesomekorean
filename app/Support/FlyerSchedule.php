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

    /** 피크 시간대 [시작, 끝) — 기본 오전 11시~오후 6시 (관리자가 flyer_peak_start_hour / flyer_peak_end_hour 로 조정) */
    public static function peakRange(): array
    {
        return [PointRules::get('flyer_peak_start_hour', 11), PointRules::get('flyer_peak_end_hour', 18)];
    }

    /** 새벽(저렴) 시간대는 자정부터 이 시각 전까지 — 기본 오전 6시 */
    public static function nightEnd(): int
    {
        return PointRules::get('flyer_night_end_hour', 6);
    }

    /** 시간(0~23)의 가격 — 센트(¢, 100 = $1). 예전 포인트 신청 건과 숫자 단위만 같다. $regionKey 는 'ALL' 또는 주 코드 */
    public static function hourPrice(string $regionKey, int $hour): int
    {
        $base = $regionKey === self::NATIONAL
            ? PointRules::get('flyer_price_national', 60)
            : PointRules::get('flyer_price_state', 30);

        [$peakStart, $peakEnd] = self::peakRange();
        $pct = 100;
        if ($hour >= $peakStart && $hour < $peakEnd) $pct = PointRules::get('flyer_peak_pct', 150);
        elseif ($hour < self::nightEnd()) $pct = PointRules::get('flyer_night_pct', 50);

        // 오픈 이벤트 기간에는 달러 결제 할인 (최소 1센트 — 0원 결제가 되지 않게)
        return OpenEvent::apply((int) round($base * $pct / 100), 'flyer_usd', 1);
    }

    /** 24시간 가격표 [hour => price] */
    public static function priceTable(string $regionKey): array
    {
        $t = [];
        for ($h = 0; $h < 24; $h++) $t[$h] = self::hourPrice($regionKey, $h);
        return $t;
    }

    public const DEFAULT_BLOCK_EDGES = '0,3,6,9,11,14,18,21,24';

    /**
     * 신청 화면의 시간 "칸"(블록) — 시간을 한 시간씩 나누지 않고 몇 시간씩 묶어서 판다.
     * 경계는 설정 flyer_block_edges (예: 0,3,6,9,11,14,18,21,24 → 자정~3시, 3~6시, … 21시~자정).
     * 잘못된 설정이면 기본값으로 되돌린다.
     *
     * @return array<int,array{start:int,end:int,hours:int[]}>
     */
    public static function blocks(): array
    {
        $edges = array_map('intval', array_filter(array_map('trim', explode(',', PointRules::raw('flyer_block_edges', self::DEFAULT_BLOCK_EDGES))), 'strlen'));
        $valid = count($edges) >= 2 && $edges[0] === 0 && end($edges) === 24 && $edges === array_values(array_unique($edges));
        if ($valid) { $sorted = $edges; sort($sorted); $valid = $sorted === $edges; }
        if (!$valid) $edges = array_map('intval', explode(',', self::DEFAULT_BLOCK_EDGES));

        $blocks = [];
        for ($i = 0; $i < count($edges) - 1; $i++) {
            $blocks[] = ['start' => $edges[$i], 'end' => $edges[$i + 1], 'hours' => range($edges[$i], $edges[$i + 1] - 1)];
        }
        return $blocks;
    }

    /** 선택한 시간들이 블록 단위(통째로 선택)인지 — 블록 일부만 고른 시간 조합이면 false */
    public static function isWholeBlocks(array $hours): bool
    {
        foreach (self::blocks() as $b) {
            $in = count(array_intersect($b['hours'], $hours));
            if ($in !== 0 && $in !== count($b['hours'])) return false;
        }
        return true;
    }

    /** 신청 합계 최소 금액(센트) — 카드 수수료 때문에 너무 작은 결제는 받지 않음 */
    public static function minOrderCents(): int
    {
        $min = max(0, PointRules::get('flyer_min_order_cents', 500));
        // 할인으로 합계가 작아져도 주문이 막히지 않도록, 이벤트 기간에는 카드사 최저 결제액(50센트)까지 허용
        return OpenEvent::discountPct('flyer_usd') > 0 ? min($min, 50) : $min;
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
