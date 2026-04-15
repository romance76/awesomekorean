<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 리소스별 상위노출 설정 헬퍼.
 * point_settings 테이블의 promo_* 키를 조회해 리소스(jobs|market|realestate|business)
 * + 티어(national|state_plus|sponsored) 조합으로 슬롯 수/가격 반환.
 *
 * 60초 캐시. 관리자 저장 시 flush() 호출로 즉시 무효화.
 */
class PromotionSettings
{
    private const CACHE_KEY = 'promotion_settings_v2';
    public const RESOURCES = ['jobs', 'market', 'realestate', 'business'];
    public const TIERS = ['national', 'state_plus', 'sponsored'];

    public static function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, function () {
            $rows = DB::table('point_settings')
                ->where('category', 'promotion')
                ->pluck('value', 'key')
                ->toArray();

            $data = ['max_slots' => [], 'price_per_day' => []];
            foreach (self::RESOURCES as $r) {
                foreach (self::TIERS as $t) {
                    // 우선 리소스별 키, 없으면 레거시 전역 키, 없으면 하드코딩 기본값
                    $slotKey = "promo_max_{$r}_{$t}";
                    $priceKey = "promo_price_{$r}_{$t}";
                    $legacySlot = "promo_max_slots_{$t}";
                    $legacyPrice = "promo_price_{$t}";

                    $slot = $t === 'sponsored'
                        ? PHP_INT_MAX
                        : (int) ($rows[$slotKey] ?? $rows[$legacySlot] ?? 5);
                    $price = (int) ($rows[$priceKey] ?? $rows[$legacyPrice] ?? self::defaultPrice($t));

                    $data['max_slots'][$r][$t] = $slot;
                    $data['price_per_day'][$r][$t] = $price;
                }
            }
            return $data;
        });
    }

    public static function maxSlots(string $tier, string $resource = 'jobs'): int
    {
        return self::all()['max_slots'][$resource][$tier] ?? 5;
    }

    public static function pricePerDay(string $tier, string $resource = 'jobs'): int
    {
        return self::all()['price_per_day'][$resource][$tier] ?? self::defaultPrice($tier);
    }

    /** 리소스 하나에 대한 전체 가격/슬롯 한번에 반환 (프론트 전송용) */
    public static function forResource(string $resource): array
    {
        $all = self::all();
        return [
            'max_slots'     => $all['max_slots'][$resource] ?? [],
            'price_per_day' => $all['price_per_day'][$resource] ?? [],
        ];
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        // v1 키도 flush (호환)
        Cache::forget('promotion_settings_v1');
    }

    private static function defaultPrice(string $tier): int
    {
        return ['national' => 100, 'state_plus' => 50, 'sponsored' => 20][$tier] ?? 0;
    }
}
