<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BannerAd;
use App\Traits\CompressesUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BannerController extends Controller
{
    use CompressesUploads;

    // 공개: 페이지/위치별 등급제 광고 가져오기 (로테이션 + 지역 타겟팅)
    public function show(Request $request)
    {
        $page = $request->page ?: 'home';
        $position = $request->position ?: 'left';

        $user = auth('api')->user();
        $userState = $user->state ?? null;
        $userCounty = $user->city ?? null;

        // Redis 캐시 (30분, 페이지+위치+지역 기준)
        $cacheKey = "banners_{$page}_{$position}_" . ($userState ?: 'all');
        $cached = Cache::get($cacheKey);
        if ($cached) {
            // 노출 수만 비동기 증가
            $ids = collect($cached)->pluck('id')->filter();
            if ($ids->count()) BannerAd::whereIn('id', $ids->toArray())->increment('impressions');
            return response()->json(['success' => true, 'data' => $cached]);
        }

        // 슬롯별 등급 정의
        $slotConfig = $position === 'left'
            ? [1 => ['tier' => 'premium', 'max' => 1], 2 => ['tier' => 'standard', 'max' => 2], 3 => ['tier' => 'economy', 'max' => 5]]
            : [1 => ['tier' => 'premium', 'max' => 1], 2 => ['tier' => 'economy', 'max' => 3]];

        $results = [];

        foreach ($slotConfig as $slotNum => $cfg) {
            $query = BannerAd::active()
                ->where('position', $position)
                ->where('slot_number', $slotNum);

            // 페이지 매칭
            $query->where(function ($q) use ($page) {
                $q->where('page', $page)
                  ->orWhere('page', 'all')
                  ->orWhereJsonContains('target_pages', $page)
                  ->orWhere('target_pages', 'LIKE', '%"' . $page . '"%');
            });

            // 지역 필터
            $nationalPages = ['home','community','qa','news','recipes','shorts','games','music','poker'];
            if (!in_array($page, $nationalPages) && $user && $userState) {
                $query->where(function ($q) use ($userState, $userCounty) {
                    $q->where('geo_scope', 'all')
                      ->orWhere(function ($q2) use ($userState) { $q2->where('geo_scope', 'state')->where('geo_value', $userState); })
                      ->orWhere(function ($q2) use ($userCounty) { $q2->where('geo_scope', 'county')->where('geo_value', $userCounty); })
                      ->orWhere(function ($q2) use ($userCounty) { $q2->where('geo_scope', 'city')->where('geo_value', $userCounty); });
                });
            }

            // 등급별 처리
            if ($cfg['tier'] === 'premium') {
                // 프리미엄: 최고 입찰자 1개 고정
                $ad = $query->orderByDesc('bid_amount')->first();
                if ($ad) $results[] = $ad;
            } else {
                // 스탠다드/이코노미: 상위 N개 중 랜덤 1개 선택
                $candidates = $query->orderByDesc('bid_amount')->limit($cfg['max'])->get();
                if ($candidates->count()) {
                    $picked = $candidates->random();
                    $results[] = $picked;
                }
            }
        }

        // 노출 수 증가
        $ids = collect($results)->pluck('id')->filter();
        if ($ids->count()) {
            BannerAd::whereIn('id', $ids)->increment('impressions');
        }

        // 30분 캐시 저장
        Cache::put($cacheKey, $results, 1800);

        return response()->json(['success' => true, 'data' => $results]);
    }

    // 모바일: 가중 랜덤 1개 광고 (프리미엄 50%, 스탠다드 30%, 이코노미 20%)
    public function mobileAd(Request $request)
    {
        $page = $request->page ?: 'home';

        // Redis 캐시 (30분)
        $mCacheKey = "banners_mobile_{$page}";
        $mCached = Cache::get($mCacheKey);
        if ($mCached) {
            if ($mCached->id ?? null) BannerAd::where('id', $mCached->id)->increment('impressions');
            return response()->json(['success' => true, 'data' => $mCached]);
        }

        $query = BannerAd::active()
            ->where(function ($q) use ($page) {
                $q->where('page', $page)->orWhere('page', 'all')
                  ->orWhereJsonContains('target_pages', $page)
                  ->orWhere('target_pages', 'LIKE', '%"' . $page . '"%');
            });

        $ads = $query->orderByDesc('bid_amount')->limit(10)->get();
        if ($ads->isEmpty()) return response()->json(['success' => true, 'data' => null]);

        // 가중 랜덤: 슬롯 1(프리미엄)=50%, 슬롯 2(스탠다드)=30%, 슬롯 3(이코노미)=20%
        $weighted = [];
        foreach ($ads as $ad) {
            $w = match((int) $ad->slot_number) { 1 => 5, 2 => 3, 3 => 2, default => 1 };
            for ($i = 0; $i < $w; $i++) $weighted[] = $ad;
        }
        $picked = $weighted[array_rand($weighted)];
        $picked->increment('impressions');
        Cache::put($mCacheKey, $picked, 1800);

        return response()->json(['success' => true, 'data' => $picked]);
    }

    // 통합: left + right + mobile 한번에 반환 (30분 Redis 캐시)
    public function all(Request $request)
    {
        $page = $request->page ?: 'home';
        $user = auth('api')->user();
        $userState = $user->state ?? null;

        $cacheKey = "banners_all_{$page}_" . ($userState ?: 'all');
        $cached = Cache::get($cacheKey);
        if ($cached) {
            // impression 증가
            $allIds = collect($cached['left'] ?? [])->pluck('id')
                ->merge(collect($cached['right'] ?? [])->pluck('id'))
                ->merge(isset($cached['mobile']->id) ? [$cached['mobile']->id] : (isset($cached['mobile']['id']) ? [$cached['mobile']['id']] : []))
                ->filter();
            if ($allIds->count()) BannerAd::whereIn('id', $allIds->toArray())->increment('impressions');
            return response()->json(['success' => true, 'data' => $cached]);
        }

        // left/right 배너 수집
        $result = ['left' => [], 'right' => [], 'mobile' => null];

        foreach (['left', 'right'] as $position) {
            $slotConfig = $position === 'left'
                ? [1 => ['tier' => 'premium', 'max' => 1], 2 => ['tier' => 'standard', 'max' => 2], 3 => ['tier' => 'economy', 'max' => 5]]
                : [1 => ['tier' => 'premium', 'max' => 1], 2 => ['tier' => 'economy', 'max' => 3]];

            foreach ($slotConfig as $slotNum => $cfg) {
                $query = BannerAd::active()->where('position', $position)->where('slot_number', $slotNum);
                $query->where(function ($q) use ($page) {
                    $q->where('page', $page)->orWhere('page', 'all')
                      ->orWhereJsonContains('target_pages', $page)
                      ->orWhere('target_pages', 'LIKE', '%"' . $page . '"%');
                });
                $nationalPages = ['home','community','qa','news','recipes','shorts','games','music','poker'];
                if (!in_array($page, $nationalPages) && $user && $userState) {
                    $userCounty = $user->city ?? null;
                    $query->where(function ($q) use ($userState, $userCounty) {
                        $q->where('geo_scope', 'all')
                          ->orWhere(function ($q2) use ($userState) { $q2->where('geo_scope', 'state')->where('geo_value', $userState); })
                          ->orWhere(function ($q2) use ($userCounty) { $q2->where('geo_scope', 'county')->where('geo_value', $userCounty); })
                          ->orWhere(function ($q2) use ($userCounty) { $q2->where('geo_scope', 'city')->where('geo_value', $userCounty); });
                    });
                }
                if ($cfg['tier'] === 'premium') {
                    $ad = $query->orderByDesc('bid_amount')->first();
                    if ($ad) $result[$position][] = $ad;
                } else {
                    $candidates = $query->orderByDesc('bid_amount')->limit($cfg['max'])->get();
                    if ($candidates->count()) $result[$position][] = $candidates->random();
                }
            }
        }

        // mobile 배너
        $mQuery = BannerAd::active()->where(function ($q) use ($page) {
            $q->where('page', $page)->orWhere('page', 'all')
              ->orWhereJsonContains('target_pages', $page)
              ->orWhere('target_pages', 'LIKE', '%"' . $page . '"%');
        });
        $mAds = $mQuery->orderByDesc('bid_amount')->limit(10)->get();
        if ($mAds->count()) {
            $weighted = [];
            foreach ($mAds as $ad) {
                $w = match((int) $ad->slot_number) { 1 => 5, 2 => 3, 3 => 2, default => 1 };
                for ($i = 0; $i < $w; $i++) $weighted[] = $ad;
            }
            $result['mobile'] = $weighted[array_rand($weighted)];
        }

        // impression + 캐시
        $allIds = collect($result['left'])->pluck('id')
            ->merge(collect($result['right'])->pluck('id'))
            ->merge($result['mobile'] ? [$result['mobile']->id] : [])
            ->filter();
        if ($allIds->count()) BannerAd::whereIn('id', $allIds->toArray())->increment('impressions');
        Cache::put($cacheKey, $result, 1800);

        return response()->json(['success' => true, 'data' => $result]);
    }

    // 배너 클릭 추적
    public function click($id)
    {
        BannerAd::where('id', $id)->increment('clicks');
        return response()->json(['success' => true]);
    }

    // 유저: 내 배너 신청 목록
    public function myBanners()
    {
        $banners = BannerAd::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->get();
        return response()->json(['success' => true, 'data' => $banners]);
    }

    // 유저: 광고 입찰 신청 (월간 경매)
    public function store(Request $request)
    {
        // URL 자동 정규화: somekorean.com → https://somekorean.com
        if ($request->link_url && !preg_match('#^https?://#i', $request->link_url)) {
            $request->merge(['link_url' => 'https://' . $request->link_url]);
        }

        $request->validate([
            'title' => 'required|max:100',
            'image' => 'required|image|max:5120',
            'link_url' => 'nullable|url',
            'page' => 'required|in:home,sub,all',
            'position' => 'required|in:left,right',
            'slot_number' => 'required|integer|min:1|max:3',
            'tier' => 'required|in:premium,standard,economy',
            'geo_scope' => 'required|in:all,state,county',
            'geo_value' => 'nullable|string|max:100',
            'bid_amount' => 'required|integer|min:50',
        ]);

        $bidAmount = (int) $request->bid_amount;
        $targetPages = json_decode($request->target_pages, true) ?: [$request->page];

        // 포인트 확인
        $user = auth()->user();
        if ($user->points < $bidAmount) {
            return response()->json([
                'success' => false,
                'message' => "포인트 부족. 입찰: {$bidAmount}P, 보유: {$user->points}P"
            ], 422);
        }

        // 배너 이미지는 광고용이라 원본 비율 유지하되 가로 1200px 로 압축
        $imagePath = $this->storeCompressedImageRaw($request->file('image'), 'banners', 1200, 85);

        // 다음 달 1일~말일로 설정
        $nextMonth = now()->addMonth();
        $startDate = $nextMonth->copy()->startOfMonth();
        $endDate = $nextMonth->copy()->endOfMonth();
        $auctionMonth = $nextMonth->format('Y-m');

        $banner = BannerAd::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'image_url' => '/storage/' . $imagePath,
            'link_url' => $request->link_url,
            'page' => $request->page,
            'target_pages' => $targetPages,
            'position' => $request->position,
            'slot_number' => $request->slot_number,
            'geo_scope' => $request->geo_scope,
            'geo_value' => $request->geo_value,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'auction_month' => $auctionMonth,
            'bid_amount' => $bidAmount,
            'daily_cost' => $bidAmount,
            'total_cost' => $bidAmount,
            'status' => 'pending',
        ]);

        // 포인트 차감
        $user->addPoints(-$bidAmount, "광고 입찰: {$request->title} ({$auctionMonth})", 'banner');

        return response()->json([
            'success' => true,
            'message' => "입찰 완료! {$bidAmount}P 차감. {$auctionMonth} 경매 결과에 따라 배정됩니다.",
            'data' => $banner,
        ], 201);
    }
}
