<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ScrapeMarketListings가 이번 커밋 전까지 lat/lng를 전혀 저장하지 않아서,
// 그동안 수집된 eBay scraped 매물이 사이트의 "내 위치 근처" 검색(scopeNearby)에
// 전혀 걸리지 않던 문제(사용자가 수와니에서 직접 확인) — 이미 저장된 행들을
// city/state 기준으로 ZIP 풀의 대략적인 좌표로 1회성 백필한다.
return new class extends Migration {
    private array $cityCoords = [
        'Suwanee|GA' => [34.0754, -84.0963],
        'Duluth|GA' => [33.9898, -84.1327],
        'Johns Creek|GA' => [34.0289, -84.1986],
        'Norcross|GA' => [33.9412, -84.2135],
        'Koreatown|CA' => [34.0511, -118.2984],
        'Irvine|CA' => [33.6839, -117.7439],
        'Flushing|NY' => [40.7675, -73.8331],
        'Fort Lee|NJ' => [40.8509, -73.9701],
        'Carrollton|TX' => [32.9890, -96.8903],
        'Federal Way|WA' => [47.3223, -122.3126],
        'Chicago|IL' => [41.9922, -87.6967],
        'Annandale|VA' => [38.8304, -77.1964],
        'Houston|TX' => [29.7752, -95.6353],
    ];

    public function up(): void
    {
        foreach ($this->cityCoords as $key => [$lat, $lng]) {
            [$city, $state] = explode('|', $key);
            DB::table('market_items')
                ->where('source', 'scraped')
                ->where('external_source', 'ebay')
                ->whereNull('lat')
                ->where('city', $city)
                ->where('state', $state)
                ->update(['lat' => $lat, 'lng' => $lng]);
        }
    }

    public function down(): void
    {
        // 1회성 데이터 백필 — 되돌릴 필요 없음
    }
};
