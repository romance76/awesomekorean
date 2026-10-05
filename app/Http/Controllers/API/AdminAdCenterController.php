<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\{BannerAd, SiteSetting, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAdCenterController extends Controller
{
    /**
     * 전체 광고 현황 (KPI + 페이지별 집계)
     */
    public function overview()
    {
        $ads = BannerAd::all();
        $activeAds = $ads->where('status', 'active');

        $pageCounts = $ads->where('status', 'active')
            ->groupBy(function($a) { return $a->page; })
            ->map(function($g) { return $g->count(); });

        return response()->json(['success' => true, 'data' => [
            'total' => $ads->count(),
            'active' => $activeAds->count(),
            'pending' => $ads->where('status', 'pending')->count(),
            'rejected' => $ads->where('status', 'rejected')->count(),
            'paused' => $ads->where('status', 'paused')->count(),
            'expired' => $ads->where('status', 'expired')->count(),
            'revenue_p' => $activeAds->sum('total_cost'),
            'total_impressions' => $ads->sum('impressions'),
            'total_clicks' => $ads->sum('clicks'),
            'page_counts' => $pageCounts,
            'pages' => $this->availablePages(),
        ]]);
    }

    /**
     * 페이지 + 지역 조합의 슬롯 맵 (시각적 뷰용)
     *
     * @param page  home|community|market|realestate|...
     * @param geo_scope  all|state|county|city
     * @param geo_value  (state: CA, county: Gwinnett, city: Duluth)
     */
    public function slotMap(Request $request)
    {
        $page = $request->query('page', 'community');
        $geoScope = $request->query('geo_scope', 'all');
        $geoValue = $request->query('geo_value');

        // 슬롯 설정 로드
        $cfgSetting = SiteSetting::where('key', 'ad_page_config')->first();
        $pageConfig = $cfgSetting ? json_decode($cfgSetting->value, true) : [];
        $thisPage = $pageConfig[$page] ?? [
            'left_slots' => 2, 'right_slots' => 2, 'label' => $page,
        ];

        // 가격 설정
        $pricesSetting = SiteSetting::where('key', 'ad_slot_min_prices')->first();
        $prices = $pricesSetting ? json_decode($pricesSetting->value, true) : [
            'left_premium' => 8000, 'left_standard' => 7000, 'left_economy' => 4000,
            'right_premium' => 10000, 'right_economy' => 6000,
        ];
        $geoSetting = SiteSetting::where('key', 'ad_geo_markup')->first();
        $geoMarkup = $geoSetting ? json_decode($geoSetting->value, true) : ['state' => 2000, 'national' => 3000];

        // 현재 배치된 광고 검색
        $query = BannerAd::with('user:id,name,email,nickname')
            ->whereIn('status', ['active', 'pending', 'paused'])
            ->where(function($q) use ($page) {
                $q->where('page', $page)->orWhereJsonContains('target_pages', $page);
            });
        if ($geoScope !== 'all') {
            $query->where('geo_scope', $geoScope);
            if ($geoValue) $query->where('geo_value', $geoValue);
        }
        $ads = $query->get();

        // 슬롯 구조 생성 (좌측: premium 1, standard 2, economy 5 / 우측: premium 1, economy 3)
        $slots = [
            'left' => $this->buildSlots('left', $prices, $ads),
            'right' => $this->buildSlots('right', $prices, $ads),
        ];

        return response()->json(['success' => true, 'data' => [
            'page' => $page,
            'page_label' => $thisPage['label'] ?? $page,
            'geo_scope' => $geoScope,
            'geo_value' => $geoValue,
            'slots' => $slots,
            'geo_markup' => $geoMarkup,
            'summary' => [
                'total_slots' => 12,
                'filled' => $ads->count(),
                'pending' => $ads->where('status', 'pending')->count(),
                'empty' => 12 - $ads->count(),
            ],
        ]]);
    }

    protected function buildSlots(string $side, array $prices, $ads): array
    {
        $layout = $side === 'left'
            ? [
                ['tier' => 'premium',  'count' => 1, 'size' => '200×140', 'price_key' => 'left_premium'],
                ['tier' => 'standard', 'count' => 2, 'size' => '200×140', 'price_key' => 'left_standard'],
                ['tier' => 'economy',  'count' => 5, 'size' => '200×140', 'price_key' => 'left_economy'],
            ]
            : [
                ['tier' => 'premium',  'count' => 1, 'size' => '300×210', 'price_key' => 'right_premium'],
                ['tier' => 'economy',  'count' => 3, 'size' => '300×210', 'price_key' => 'right_economy'],
            ];

        $result = [];
        $usedAdIds = [];
        foreach ($layout as $tier) {
            $slotNum = $this->tierToSlotNum($tier['tier']);
            $tierAds = $ads->where('position', $side)->where('slot_number', $slotNum)
                ->whereNotIn('id', $usedAdIds)
                ->take($tier['count'])
                ->values();
            foreach ($tierAds as $a) $usedAdIds[] = $a->id;

            $slotsInTier = [];
            for ($i = 0; $i < $tier['count']; $i++) {
                $ad = $tierAds[$i] ?? null;
                $slotsInTier[] = [
                    'index' => $i + 1,
                    'ad' => $ad ? [
                        'id' => $ad->id,
                        'title' => $ad->title,
                        'image_url' => $ad->image_url,
                        'link_url' => $ad->link_url,
                        'status' => $ad->status,
                        'impressions' => (int)$ad->impressions,
                        'clicks' => (int)$ad->clicks,
                        'total_cost' => (int)$ad->total_cost,
                        'start_date' => $ad->start_date,
                        'end_date' => $ad->end_date,
                        'user' => [
                            'id' => $ad->user?->id,
                            'name' => $ad->user?->nickname ?: $ad->user?->name,
                            'email' => $ad->user?->email,
                        ],
                    ] : null,
                ];
            }
            $result[] = [
                'tier' => $tier['tier'],
                'count' => $tier['count'],
                'size' => $tier['size'],
                'price' => $prices[$tier['price_key']] ?? 0,
                'slots' => $slotsInTier,
            ];
        }
        return $result;
    }

    protected function tierToSlotNum(string $tier): int
    {
        return match ($tier) {
            'premium' => 1,
            'standard' => 2,
            'economy' => 3,
            default => 0,
        };
    }

    /**
     * 광고를 걸 수 있는 페이지 목록 — 예전엔 여기 13개짜리 고정 목록을 직접
     * 하드코딩해둬서, 관리자가 "메뉴 관리"에서 페이지별로 광고 on/off·종류
     * (일반/애드센스)를 설정해도 광고 센터·광고 목록엔 전혀 반영이 안 됐음.
     * 이제 site_settings.menu_config를 직접 읽어서, 활성화(enabled)이면서
     * 광고가 켜져(ads_enabled) 있는 메뉴만 동적으로 뽑아옴 — 관리자가 메뉴
     * 관리에서 체크/해제한 그대로 광고 센터 페이지 선택지에 반영됨.
     */
    protected function availablePages(): array
    {
        $geoKeys = ['market', 'jobs', 'realestate', 'directory', 'clubs', 'events'];
        $setting = SiteSetting::where('key', 'menu_config')->first();
        $menus = $setting ? json_decode($setting->value, true) : null;

        if (!is_array($menus) || empty($menus)) {
            // menu_config가 아직 없는 최초 상태 대비 — 과거 고정 목록을 전부
            // 일반 광고 대상으로 간주해 폴백 (기존 운영 상태와 동일하게 유지)
            $menus = collect([
                ['key' => 'home', 'label' => '홈', 'icon' => '🏠'],
                ['key' => 'community', 'label' => '커뮤니티', 'icon' => '💬'],
                ['key' => 'qa', 'label' => 'Q&A', 'icon' => '❓'],
                ['key' => 'news', 'label' => '뉴스', 'icon' => '📰'],
                ['key' => 'info', 'label' => '정보', 'icon' => '📘'],
                ['key' => 'recipes', 'label' => '레시피', 'icon' => '🍳'],
                ['key' => 'groupbuy', 'label' => '공동구매', 'icon' => '🛍'],
                ['key' => 'music', 'label' => '음악', 'icon' => '🎵'],
                ['key' => 'shorts', 'label' => '숏츠', 'icon' => '🎬'],
                ['key' => 'market', 'label' => '중고장터', 'icon' => '🛒'],
                ['key' => 'jobs', 'label' => '구인구직', 'icon' => '💼'],
                ['key' => 'realestate', 'label' => '부동산', 'icon' => '🏠'],
                ['key' => 'directory', 'label' => '업소록', 'icon' => '🏪'],
                ['key' => 'clubs', 'label' => '동호회', 'icon' => '👥'],
                ['key' => 'events', 'label' => '이벤트', 'icon' => '🎉'],
            ])->map(fn($m) => $m + ['enabled' => true, 'ads_enabled' => true, 'ads_type' => 'banner'])->all();
        }

        $pages = [];
        foreach ($menus as $m) {
            $key = $m['key'] ?? null;
            if (!$key) continue;
            if (($m['enabled'] ?? true) === false) continue;
            if (empty($m['ads_enabled'])) continue;
            $pages[$key] = [
                'label' => $m['label'] ?? $key,
                'geo' => in_array($key, $geoKeys, true),
                'icon' => $m['icon'] ?? '📄',
                'ads_type' => $m['ads_type'] ?? 'banner',
            ];
        }
        return $pages;
    }

    /**
     * 광고 상세 (광고주 + 결제 + 통계)
     */
    public function bannerDetail($id)
    {
        $ad = BannerAd::with('user:id,name,email,nickname,phone,city,state')->findOrFail($id);

        // 이 광고주의 다른 광고
        $otherAds = BannerAd::where('user_id', $ad->user_id)
            ->where('id', '!=', $id)
            ->orderByDesc('created_at')->limit(10)->get(['id', 'title', 'page', 'position', 'status', 'total_cost']);

        // 이 광고주의 포인트 사용 히스토리 (광고 관련)
        $pointLogs = \App\Models\PointLog::where('user_id', $ad->user_id)
            ->whereIn('type', ['banner', 'banner_refund'])
            ->orderByDesc('created_at')->limit(20)->get();

        return response()->json(['success' => true, 'data' => [
            'ad' => $ad,
            'other_ads' => $otherAds,
            'point_logs' => $pointLogs,
        ]]);
    }

    /**
     * 지역 자동완성 (target 지역 선택 시)
     */
    public function geoList(Request $request)
    {
        $scope = $request->query('scope', 'state');
        $search = $request->query('search', '');

        // 기존 banner_ads 에서 사용된 지역 + users 테이블에서 활성 지역 수집
        $query = BannerAd::query()
            ->where('geo_scope', $scope)
            ->whereNotNull('geo_value')
            ->select('geo_value')
            ->groupBy('geo_value')
            ->selectRaw('COUNT(*) as cnt');
        if ($search) $query->where('geo_value', 'like', "%{$search}%");
        $items = $query->orderByDesc('cnt')->limit(100)->get();

        return response()->json(['success' => true, 'data' => $items]);
    }
}
