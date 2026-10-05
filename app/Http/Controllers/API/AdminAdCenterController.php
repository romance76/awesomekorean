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
     * 광고를 걸 수 있는 페이지 목록 — 사이트 전체 메뉴(site_settings.menu_config,
     * 17개 활성)와 별개로 여기 직접 하드코딩돼 있다 보니, '정보'·'숏츠'처럼
     * 나중에 추가된 공개 콘텐츠 페이지가 광고 센터 페이지 선택지/광고 목록
     * 탭 어디에도 안 뜨는 문제가 있었음(실측: 17개 활성 메뉴 중 13개만 반영돼
     * 있었음). 채팅/친구처럼 개인 전용 기능 페이지는 광고 지면으로 의미가
     * 없어 제외하고, 공개 콘텐츠 페이지만 계속 직접 관리.
     */
    protected function availablePages(): array
    {
        return [
            'home' => ['label' => '홈', 'geo' => false, 'icon' => '🏠'],
            'community' => ['label' => '커뮤니티', 'geo' => false, 'icon' => '💬'],
            'qa' => ['label' => 'Q&A', 'geo' => false, 'icon' => '❓'],
            'news' => ['label' => '뉴스', 'geo' => false, 'icon' => '📰'],
            'info' => ['label' => '정보', 'geo' => false, 'icon' => '📘'],
            'recipes' => ['label' => '레시피', 'geo' => false, 'icon' => '🍳'],
            'groupbuy' => ['label' => '공동구매', 'geo' => false, 'icon' => '🛍'],
            'music' => ['label' => '음악', 'geo' => false, 'icon' => '🎵'],
            'shorts' => ['label' => '숏츠', 'geo' => false, 'icon' => '🎬'],
            'market' => ['label' => '중고장터', 'geo' => true, 'icon' => '🛒'],
            'jobs' => ['label' => '구인구직', 'geo' => true, 'icon' => '💼'],
            'realestate' => ['label' => '부동산', 'geo' => true, 'icon' => '🏠'],
            'directory' => ['label' => '업소록', 'geo' => true, 'icon' => '🏪'],
            'clubs' => ['label' => '동호회', 'geo' => true, 'icon' => '👥'],
            'events' => ['label' => '이벤트', 'geo' => true, 'icon' => '🎉'],
        ];
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
