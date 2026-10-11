<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BannerAd;
use App\Models\SiteSetting;
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

    /**
     * 텍스트 인라인 광고 신청 (이미지 없음, 간단 필드만)
     * POST /api/banners/text-apply
     */
    public function textApply(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:80',
            'phone'       => 'nullable|string|max:30',
            'description' => 'required|string|max:200',
            'link_url'    => 'nullable|url|max:500',
            'page'        => 'required|string|max:40',
            'days'        => 'required|integer|min:1|max:365',
            'bid_amount'  => 'required|integer|min:100',
        ]);

        $user = auth()->user();
        $bid = (int) $data['bid_amount'];   // 입찰액(센트 단위 — 예전 포인트 숫자 그대로, 100 = $1.00)
        // Kay 결정(2026-10-11): 광고는 카드 결제만. 오픈 이벤트 할인은 청구액에만(입찰 순위는 그대로), Stripe 최소 $0.50
        $cost = \App\Support\AdPayments::cents(\App\Support\OpenEvent::apply($bid, 'banner'));

        $ad = BannerAd::create([
            'user_id'     => $user->id,
            'title'       => $data['title'],
            'image_url'   => null,
            'link_url'    => $data['link_url'] ?? null,
            'ad_type'     => 'text',
            'phone'       => $data['phone'] ?? null,
            'description' => $data['description'],
            'page'        => $data['page'],
            'position'    => 'inline-text',
            'slot_number' => 1,
            'geo_scope'   => 'all',
            'bid_amount'  => $bid,
            'daily_cost'  => (int) ceil($cost / $data['days']),
            'total_cost'  => $cost,
            'start_date'  => now()->toDateString(),
            'end_date'    => now()->addDays($data['days'])->toDateString(),
            'status'      => 'awaiting_payment', // 카드 확인 → pending(승인 대기) → 관리자 승인 → active
            'payment_method' => 'card',
        ]);

        try {
            $secret = \App\Support\AdPayments::begin($user, $ad, \App\Models\Payment::KIND_TEXT_AD, $cost,
                "텍스트 광고: {$ad->title} ({$data['days']}일)");
        } catch (\Throwable $e) {
            \Log::error('텍스트 광고 결제 생성 실패: ' . $e->getMessage());
            $ad->delete();
            return response()->json(['success' => false, 'message' => '카드 결제를 시작하지 못했어요. 잠시 후 다시 시도해 주세요.'], 502);
        }

        return response()->json([
            'success' => true,
            'message' => '카드를 확인해 주세요. 지금은 청구되지 않고, 관리자가 승인하면 그때 청구돼요.',
            'data'    => ['banner_id' => $ad->id, 'client_secret' => $secret, 'total_cents' => $cost],
        ], 201);
    }

    /**
     * POST /api/banners/{id}/confirm-payment — 카드 확인(보류)이 끝났음을 알림. Stripe 에서 직접 확인한 뒤 승인 대기로.
     */
    public function confirmPayment(Request $request, $id)
    {
        $ad = BannerAd::where('user_id', $request->user()->id)->findOrFail($id);
        if ($ad->status === 'pending') {
            return response()->json(['success' => true, 'message' => '이미 접수된 신청이에요.', 'data' => $ad]);
        }
        if ($ad->status !== 'awaiting_payment' || !\App\Support\AdPayments::isCard($ad)) {
            return response()->json(['success' => false, 'message' => '결제를 진행 중인 신청이 아니에요. 처음부터 다시 신청해 주세요.'], 422);
        }
        try {
            \App\Support\AdPayments::confirm($ad);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('광고 결제 확인 실패: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => '결제 확인 중 오류가 났어요. 잠시 후 다시 시도해 주세요.'], 502);
        }
        $ad->update(['status' => 'pending']);
        \Cache::forget("banner_text_inline_{$ad->page}");
        \Cache::forget("banner_text_inline_all");
        $this->notifyAdminsNewAd($request->user(), $ad->title);

        $money = \App\Support\AdPayments::money((int) $ad->total_cost);
        return response()->json([
            'success' => true,
            'message' => "신청이 접수됐어요. 관리자 승인 후 게재되고, 그때 카드에 {$money}가 청구돼요.",
            'data' => $ad,
        ]);
    }

    /** POST /api/banners/{id}/cancel — 승인 전(결제 대기·승인 대기)에만 취소. 카드는 청구 없이 보류 해제 */
    public function cancel(Request $request, $id)
    {
        $ad = BannerAd::where('user_id', $request->user()->id)->findOrFail($id);
        if (!in_array($ad->status, ['awaiting_payment', 'pending'], true)) {
            return response()->json(['success' => false, 'message' => '승인 전인 신청만 취소할 수 있어요.'], 422);
        }
        if (!\App\Support\AdPayments::isCard($ad)) {
            return response()->json(['success' => false, 'message' => '예전에 포인트로 신청한 광고는 관리자에게 취소를 요청해 주세요.'], 422);
        }
        \App\Support\AdPayments::release($ad);
        $ad->update(['status' => 'cancelled']);
        return response()->json(['success' => true, 'message' => '취소했어요. 카드에는 청구되지 않았어요.']);
    }

    // 새 광고 신청을 관리자(광고 목록을 볼 수 있는 관리자 이상)에게 알림 — 누르면 승인 대기 광고 목록이 열린다
    private function notifyAdminsNewAd($user, string $title): void
    {
        try {
            $adminIds = \App\Models\User::whereIn('role', ['admin', 'super_admin'])->pluck('id');
            foreach ($adminIds as $adminId) {
                \App\Models\Notification::create(['user_id' => $adminId, 'type' => 'banner_submitted', 'title' => '새 광고 신청이 도착했습니다',
                    'content' => "{$user->name}님이 '{$title}' 광고를 신청했어요 (카드 보류 완료).", 'data' => ['url' => '/admin/banners?status=pending']]);
                $unread = \App\Models\Notification::where('user_id', $adminId)->whereNull('read_at')->count();
                broadcast(new \App\Events\NewNotification($adminId, $unread, '새 광고 신청이 도착했습니다'))->toOthers();
            }
        } catch (\Exception $e) {}
    }

    /**
     * 텍스트 인라인 광고 (리스트 중간 · 상세 본문 하단 공용)
     * GET /api/banners/text-inline?page=community
     *   ad_type='text' + position='inline-text' 중 bid_amount 최고가 확정 + 타입 필터
     *   10분 Redis 캐시
     */
    public function textInline(Request $request)
    {
        $page = $request->page ?: 'home';
        $cacheKey = "banner_text_inline_{$page}";
        $cached = \Cache::get($cacheKey);
        if ($cached) {
            if (isset($cached->id)) BannerAd::where('id', $cached->id)->increment('impressions');
            return response()->json(['success' => true, 'data' => $cached]);
        }

        // 상위 10개 중 bid_amount 가중 랜덤 (높은 입찰가가 더 자주 노출)
        $ads = BannerAd::active()
            ->where('ad_type', 'text')
            ->where('position', 'inline-text')
            ->where(function ($q) use ($page) {
                $q->where('page', $page)->orWhere('page', 'all')
                  ->orWhereJsonContains('target_pages', $page)
                  ->orWhere('target_pages', 'LIKE', '%"' . $page . '"%');
            })
            ->orderByDesc('bid_amount')
            ->limit(10)
            ->get();

        if ($ads->isEmpty()) return response()->json(['success' => true, 'data' => null]);

        // 가중치 = bid_amount / 100 (최소 1). 높은 입찰 = 더 자주.
        $weighted = [];
        foreach ($ads as $a) {
            $w = max(1, (int) ($a->bid_amount / 100));
            for ($i = 0; $i < $w; $i++) $weighted[] = $a;
        }
        $ad = $weighted[array_rand($weighted)];

        $ad->increment('impressions');
        \Cache::put($cacheKey, $ad, 300); // 5분 회전
        return response()->json(['success' => true, 'data' => $ad]);
    }

    /**
     * 모바일 리스트/상세 배너 슬롯 — 4개 광고 가중 랜덤 회전
     * GET /api/banners/mobile-slot?page=community
     *
     * 규칙:
     *   데스크톱 사이드바 4슬롯 (프리미엄A=left/1, 스탠다드A=left/2, 프리미엄B=right/1, 스탠다드B=right/2) 의
     *   최고가 1명씩 풀로 수집 → 한 자리에서 35:35:15:15 가중 랜덤.
     *   - 프리미엄 A/B = 각 35% (합 70%)
     *   - 스탠다드 A/B = 각 15% (합 30%)
     *
     * 페이지당 5분 Redis 캐시 (너무 자주 바뀌면 회전 애니메이션 부자연스러움).
     */
    public function mobileSlot(Request $request)
    {
        $page = $request->page ?: 'home';
        $cacheKey = "banner_mobile_rotation_{$page}";
        $cached = \Cache::get($cacheKey);
        if ($cached) {
            if (isset($cached->id)) BannerAd::where('id', $cached->id)->increment('impressions');
            return response()->json(['success' => true, 'data' => $cached]);
        }

        $pageMatch = function ($q) use ($page) {
            $q->where('page', $page)->orWhere('page', 'all')
              ->orWhereJsonContains('target_pages', $page)
              ->orWhere('target_pages', 'LIKE', '%"' . $page . '"%');
        };

        // 4개 슬롯별 최고가 광고 수집
        $slots = [
            ['left',  1, 35], // 프리미엄 A
            ['right', 1, 35], // 프리미엄 B
            ['left',  2, 15], // 스탠다드 A
            ['right', 2, 15], // 스탠다드 B
        ];

        $pool = []; // [[ad, weight], ...]
        foreach ($slots as [$pos, $slotNum, $weight]) {
            $ad = BannerAd::active()
                ->where('position', $pos)
                ->where('slot_number', $slotNum)
                ->where(function ($q) use ($pageMatch) { $pageMatch($q); })
                ->orderByDesc('bid_amount')
                ->first();
            if ($ad) $pool[] = [$ad, $weight];
        }

        if (empty($pool)) return response()->json(['success' => true, 'data' => null]);

        // 가중 랜덤 뽑기
        $weighted = [];
        foreach ($pool as [$ad, $w]) {
            for ($i = 0; $i < $w; $i++) $weighted[] = $ad;
        }
        $picked = $weighted[array_rand($weighted)];

        $picked->increment('impressions');
        \Cache::put($cacheKey, $picked, 300); // 5분 회전
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
        // URL 자동 정규화: awesomekorean.com → https://awesomekorean.com
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

        // 화면에 실제로 없는 (페이지, 위치) 조합은 신규 신청 불가 — 예: 이벤트 페이지 오른쪽
        foreach ((array) $targetPages as $tp) {
            if (!is_string($tp) || in_array($tp, ['all', 'sub'], true)) continue;
            if (!\App\Support\AdSlotMap::exists($tp, (string) $request->position)) {
                return response()->json([
                    'success' => false,
                    'message' => '선택한 페이지에는 해당 광고 자리가 없습니다. 다른 자리를 선택해 주세요.',
                ], 422);
            }
        }

        // 관리자가 AdminAdCenter(슬롯맵)에서 설정한 슬롯별 최저가가 실제로는
        // 강제되지 않고 하드코딩된 min:50만 통과하면 되던 문제 수정
        // (AdminAdCenterController::slotMap()과 동일한 설정/기본값 사용).
        $pricesSetting = SiteSetting::where('key', 'ad_slot_min_prices')->first();
        $minPrices = $pricesSetting ? json_decode($pricesSetting->value, true) : [
            'left_premium' => 8000, 'left_standard' => 7000, 'left_economy' => 4000,
            'right_premium' => 10000, 'right_economy' => 6000,
        ];
        $priceKey = "{$request->position}_{$request->tier}";
        $minPrice = (int) ($minPrices[$priceKey] ?? 50);
        if ($bidAmount < $minPrice) {
            return response()->json([
                'success' => false,
                'message' => '이 자리의 최소 입찰가는 ' . \App\Support\AdPayments::money($minPrice) . ' 이에요 (입력: ' . \App\Support\AdPayments::money($bidAmount) . ')',
            ], 422);
        }

        // Kay 결정(2026-10-11): 광고는 카드 결제만. 입찰액은 센트 단위(예전 포인트 숫자 그대로, 100 = $1.00).
        // 오픈 이벤트 할인은 청구액에만(입찰 순위는 그대로), Stripe 최소 $0.50.
        $user = auth()->user();
        $chargeAmount = \App\Support\AdPayments::cents(\App\Support\OpenEvent::apply($bidAmount, 'banner'));

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
            'daily_cost' => $chargeAmount,
            'total_cost' => $chargeAmount,
            'status' => 'awaiting_payment',   // 카드 확인 → pending(승인 대기) → 관리자 승인 시 청구·게재
            'payment_method' => 'card',
        ]);

        try {
            $secret = \App\Support\AdPayments::begin($user, $banner, \App\Models\Payment::KIND_BANNER, $chargeAmount,
                "배너 광고: {$request->title} ({$auctionMonth})");
        } catch (\Throwable $e) {
            \Log::error('배너 광고 결제 생성 실패: ' . $e->getMessage());
            $banner->delete();
            return response()->json(['success' => false, 'message' => '카드 결제를 시작하지 못했어요. 잠시 후 다시 시도해 주세요.'], 502);
        }
        // 관리자 알림은 카드 확인이 끝난 뒤(confirmPayment)에 보낸다

        return response()->json([
            'success' => true,
            'message' => '카드를 확인해 주세요. 지금은 청구되지 않고, 관리자가 승인하면 그때 청구돼요.',
            'data' => ['banner_id' => $banner->id, 'client_secret' => $secret, 'total_cents' => $chargeAmount, 'auction_month' => $auctionMonth],
        ], 201);
    }
}
