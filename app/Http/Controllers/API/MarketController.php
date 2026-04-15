<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MarketItem;
use App\Models\MarketReservation;
use App\Models\User;
use App\Traits\AdminAuthorizes;
use App\Traits\CompressesUploads;
use App\Traits\HasPromotions;
use Illuminate\Http\Request;
use Carbon\Carbon;

class MarketController extends Controller
{
    use AdminAuthorizes, CompressesUploads, HasPromotions;

    protected string $promoResource = 'market';
    protected string $promoModel = MarketItem::class;
    protected string $promoCategoryColumn = 'category';

    public function promote(Request $request, $id)
    {
        $item = $this->findOwnedOrAdmin(MarketItem::class, $id);
        return $this->handlePromote($item, $request);
    }

    public function promotionSlots(Request $request)
    {
        return $this->handlePromotionSlots($request);
    }

    public function index(Request $request)
    {
        $this->expireStalePromotions();

        $query = MarketItem::with('user:id,name,nickname')
            ->when($request->user_id, fn($q, $v) => $q->where('user_id', $v)->whereIn('status', ['active','reserved','sold']),
                fn($q) => $q->whereIn('status', ['active', 'reserved']))
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            ->when($request->condition, fn($q, $v) => $q->where('condition', $v))
            ->when($request->search, fn($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->when($request->min_price, fn($q, $v) => $q->where('price', '>=', $v))
            ->when($request->max_price, fn($q, $v) => $q->where('price', '<=', $v));

        $hasLocation = $request->lat && $request->lng;
        if ($hasLocation) {
            $query->nearby($request->lat, $request->lng, $request->radius ?? 10);
        }

        // 위치 모드별 프로모션 티어 제외 + 프로모션 우선 정렬
        $this->excludeCrossTierPromotion($query, $hasLocation);
        $this->applyPromotionOrdering($query, $request->user_state, $hasLocation);

        if ($hasLocation) {
            // 위치 정렬 후 기본 최신/부스트
            $query->orderByRaw('CASE WHEN boosted_until > NOW() THEN 0 ELSE 1 END')
                  ->orderByRaw('COALESCE(bumped_at, created_at) DESC');
        } else {
            $query->orderByRaw('CASE WHEN boosted_until > NOW() THEN 0 ELSE 1 END')
                  ->orderByRaw('COALESCE(bumped_at, created_at) DESC');
        }

        return response()->json(['success' => true, 'data' => $query->paginate($request->per_page ?? 20)]);
    }

    public function show($id)
    {
        $item = MarketItem::with(['user:id,name,nickname,avatar', 'reservations' => function($q) {
            $q->where('status', 'pending')->where('hold_until', '>', now())->with('buyer:id,name,nickname');
        }])->findOrFail($id);
        $item->increment('view_count');

        // 현재 활성 홀드 정보
        $activeHold = $item->reservations->first();
        $item->active_hold = $activeHold;

        return response()->json(['success' => true, 'data' => $item]);
    }

    public function store(Request $request)
    {
        // FormData 에서 boolean 은 문자열로 오므로 merge 전에 변환
        $request->merge([
            'hold_enabled' => filter_var($request->hold_enabled, FILTER_VALIDATE_BOOLEAN),
            'is_negotiable' => filter_var($request->is_negotiable, FILTER_VALIDATE_BOOLEAN),
        ]);

        // 관리자 설정 로드
        $getSetting = fn($key, $default) => (int) (\DB::table('point_settings')->where('key', $key)->value('value') ?? $default);
        $maxPerCategory = $getSetting('market_max_same_category_daily', 1);
        $maxPhotos = $getSetting('market_max_photos', 10);
        $freePhotos = $getSetting('market_free_photos', 5);
        $extraPhotoPoints = $getSetting('market_extra_photo_cost', 50);

        $request->validate([
            'title' => 'required|max:200',
            'content' => 'required',
            'price' => 'required|numeric|min:0',
            'category' => 'required',
            'condition' => 'required',
            'hold_enabled' => 'nullable|boolean',
            'hold_price_per_6h' => 'nullable|integer|min:0',
            'hold_max_hours' => 'nullable|integer|min:6|max:168',
            'images' => "nullable|array|max:{$maxPhotos}",
            'images.*' => 'nullable|image|max:10240',
        ]);

        // 동일 카테고리 하루 제한 (기본 1건)
        $user = auth()->user();
        $todaySameCategory = MarketItem::where('user_id', $user->id)
            ->where('category', $request->category)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($todaySameCategory >= $maxPerCategory) {
            return response()->json(['success' => false, 'message' => "동일 카테고리에 24시간 내 최대 {$maxPerCategory}건만 등록 가능합니다."], 422);
        }

        // 동일 제목 중복 등록 방지
        $duplicateTitle = MarketItem::where('user_id', $user->id)
            ->where('title', $request->title)
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();
        if ($duplicateTitle) {
            return response()->json(['success' => false, 'message' => '동일한 제목의 물품이 이미 등록되어 있습니다.'], 422);
        }

        // 이미지 업로드 + 압축 저장
        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                // 중고장터 사진은 800px + 품질 75 로 충분 (서버 용량 절약 핵심 포인트)
                $images[] = $this->storeCompressedImageRaw($img, 'market', 800, 75);
            }
        }

        // 추가 사진 포인트 차감 (관리자 설정 기반)
        $extraCount = max(0, count($images) - $freePhotos);
        $extraCost = $extraCount * $extraPhotoPoints;
        if ($extraCost > 0) {
            $user = $user->fresh();
            if ($user->points < $extraCost) {
                return response()->json([
                    'success' => false,
                    'message' => "추가 사진 {$extraCount}장에 {$extraCost}P 필요 (무료 {$freePhotos}장 초과). 보유: {$user->points}P"
                ], 422);
            }
            $user->addPoints(-$extraCost, "추가 사진 {$extraCount}장 ({$extraPhotoPoints}P/장)", 'photo');
        }

        // 위치 정보 없으면 유저 프로필에서 가져오기
        $lat = $request->lat ?: $user->latitude;
        $lng = $request->lng ?: $user->longitude;
        $city = $request->city ?: $user->city;
        $state = $request->state ?: $user->state;

        $thumbIdx = max(0, min(count($images) - 1, (int) ($request->thumbnail_index ?? 0)));

        $item = MarketItem::create(array_merge(
            $request->only('title', 'content', 'price', 'category', 'condition', 'is_negotiable', 'hold_enabled', 'hold_price_per_6h', 'hold_max_hours'),
            [
                'user_id' => $user->id, 'images' => $images ?: null,
                'thumbnail_index' => $thumbIdx,
                'lat' => $lat, 'lng' => $lng, 'city' => $city, 'state' => $state,
            ]
        ));

        return response()->json(['success' => true, 'data' => $item], 201);
    }

    public function update(Request $request, $id)
    {
        $item = $this->findOwnedOrAdmin(MarketItem::class, $id);
        $data = $request->only('title', 'content', 'price', 'category', 'condition', 'status', 'is_negotiable', 'hold_enabled', 'hold_price_per_6h', 'hold_max_hours');
        if ($request->has('thumbnail_index')) {
            $max = is_array($item->images) ? count($item->images) - 1 : 0;
            $data['thumbnail_index'] = max(0, min($max, (int) $request->thumbnail_index));
        }
        $item->update($data);
        return response()->json(['success' => true, 'data' => $item]);
    }

    // 끌어올리기 (범프) — 기존 글을 오늘 날짜로 상위 노출
    public function bump($id)
    {
        $user = auth()->user();
        $item = MarketItem::where('user_id', $user->id)->findOrFail($id);

        // 관리자 설정 로드
        $getSetting = fn($key, $default) => (int) (\DB::table('point_settings')->where('key', $key)->value('value') ?? $default);
        $bumpBaseCost = $getSetting('market_bump_base_cost', 100);
        $bumpIncrement = $getSetting('market_bump_increment', 200);
        $bumpMaxTimes = $getSetting('market_bump_max_times', 5);
        $bumpCooldownHours = $getSetting('market_bump_cooldown_hours', 24);

        // 범프 횟수 확인
        $bumpCount = $item->bump_count ?? 0;
        if ($bumpCount >= $bumpMaxTimes) {
            return response()->json(['success' => false, 'message' => "최대 {$bumpMaxTimes}회까지만 끌어올리기 가능합니다."], 422);
        }

        // 24시간 쿨다운 확인
        $lastBump = $item->last_bumped_at;
        if ($lastBump && now()->diffInHours($lastBump) < $bumpCooldownHours) {
            $remaining = $bumpCooldownHours - now()->diffInHours($lastBump);
            return response()->json(['success' => false, 'message' => "끌어올리기는 {$bumpCooldownHours}시간 간격으로 가능합니다. {$remaining}시간 후 가능."], 422);
        }

        // 비용 계산: 100, 300, 500, 700, 900 (기본100 + 횟수×200)
        $cost = $bumpBaseCost + ($bumpCount * $bumpIncrement);

        if ($user->points < $cost) {
            return response()->json(['success' => false, 'message' => "포인트 부족. 필요: {$cost}P ({$bumpCount}+1회차), 보유: {$user->points}P"], 422);
        }

        // 포인트 차감 + 범프 처리
        $user->addPoints(-$cost, "장터 끌어올리기: {$item->title} ({$bumpCount}+1회)", 'bump');
        $item->update([
            'bump_count' => $bumpCount + 1,
            'last_bumped_at' => now(),
            'bumped_at' => now(), // 정렬 기준 날짜 (created_at은 원본 유지)
        ]);

        return response()->json([
            'success' => true,
            'message' => "끌어올리기 완료! {$cost}P 차감. ({$bumpCount}+1/{$bumpMaxTimes}회)",
            'data' => $item->fresh(),
        ]);
    }

    public function destroy($id)
    {
        $this->findOwnedOrAdmin(MarketItem::class, $id)->delete();
        return response()->json(['success' => true, 'message' => '삭제되었습니다']);
    }

    // ─────────────────────── 홀드 시스템 ───────────────────────

    /**
     * 구매자가 홀드 요청
     * POST /api/market/{id}/hold
     * { hours: 6|12|24|48|72|168 }
     */
    public function hold(Request $request, $id)
    {
        $request->validate(['hours' => 'required|integer|min:6|max:168']);
        $hours = (int) $request->hours;

        $item = MarketItem::findOrFail($id);
        $buyer = auth()->user();

        // 검증
        if ($item->user_id === $buyer->id) {
            return response()->json(['success' => false, 'message' => '본인 물건은 홀드할 수 없습니다'], 422);
        }
        if (!$item->hold_enabled) {
            return response()->json(['success' => false, 'message' => '이 물건은 홀드를 지원하지 않습니다'], 422);
        }
        if ($item->status !== 'active') {
            return response()->json(['success' => false, 'message' => '이미 예약되거나 판매된 물건입니다'], 422);
        }
        if ($hours > $item->hold_max_hours) {
            return response()->json(['success' => false, 'message' => "최대 {$item->hold_max_hours}시간까지 홀드 가능합니다"], 422);
        }

        // 활성 홀드 있는지 확인
        $existingHold = MarketReservation::where('market_item_id', $id)
            ->where('status', 'pending')
            ->where('hold_until', '>', now())
            ->exists();
        if ($existingHold) {
            return response()->json(['success' => false, 'message' => '이미 다른 사람이 홀드 중입니다'], 422);
        }

        // 비용 계산: (시간 / 6) × 6시간당 가격
        $blocks = ceil($hours / 6);
        $totalCost = $blocks * $item->hold_price_per_6h;
        $commission = (int) round($totalCost * 0.10); // 10% 수수료
        $sellerReceived = $totalCost - $commission;

        // 포인트 확인
        if ($buyer->points < $totalCost) {
            return response()->json([
                'success' => false,
                'message' => "포인트가 부족합니다. 필요: {$totalCost}P, 보유: {$buyer->points}P"
            ], 422);
        }

        // 포인트 차감/지급 (point_logs 에 기록)
        $buyer->addPoints(-$totalCost, "홀드: {$item->title} ({$hours}시간)", 'hold');
        User::find($item->user_id)?->addPoints($sellerReceived, "홀드 수입: {$item->title}", 'hold_income');

        // 예약 생성
        $reservation = MarketReservation::create([
            'market_item_id' => $id,
            'buyer_id' => $buyer->id,
            'seller_id' => $item->user_id,
            'points_held' => $totalCost,
            'hold_until' => now()->addHours($hours),
            'hold_hours' => $hours,
            'commission' => $commission,
            'seller_received' => $sellerReceived,
            'status' => 'pending',
        ]);

        // 아이템 상태 변경
        $item->update(['status' => 'reserved']);

        return response()->json([
            'success' => true,
            'message' => "{$hours}시간 홀드 완료! {$totalCost}P 차감됨 (판매자 {$sellerReceived}P, 수수료 {$commission}P)",
            'data' => $reservation,
        ]);
    }

    /**
     * 홀드 취소 (구매자 또는 판매자)
     * POST /api/market/{id}/hold/cancel
     */
    public function cancelHold($id)
    {
        $item = MarketItem::findOrFail($id);
        $user = auth()->user();

        $reservation = MarketReservation::where('market_item_id', $id)
            ->where('status', 'pending')
            ->where(function($q) use ($user) {
                $q->where('buyer_id', $user->id)->orWhere('seller_id', $user->id);
            })
            ->first();

        if (!$reservation) {
            return response()->json(['success' => false, 'message' => '활성 홀드가 없습니다'], 404);
        }

        $reservation->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $item->update(['status' => 'active']);

        // 홀드 포인트는 환불 안 함 (서비스 이용료 개념)

        return response()->json(['success' => true, 'message' => '홀드가 취소되었습니다']);
    }

    // ─────────────────────── 상위노출(부스트) ───────────────────────

    /**
     * 판매자가 자기 물건을 상위 노출 (부스트)
     * POST /api/market/{id}/boost
     * { days: 1|3|7 }
     */
    public function boost(Request $request, $id)
    {
        $request->validate(['days' => 'required|integer|in:1,3,7']);
        $days = (int) $request->days;

        $item = MarketItem::where('user_id', auth()->id())->findOrFail($id);
        $user = auth()->user();

        if ($item->status !== 'active') {
            return response()->json(['success' => false, 'message' => '활성 상태의 물건만 부스트 가능합니다'], 422);
        }

        $costPerDay = 100; // 하루 100포인트
        $totalCost = $days * $costPerDay;

        if ($user->points < $totalCost) {
            return response()->json([
                'success' => false,
                'message' => "포인트가 부족합니다. 필요: {$totalCost}P, 보유: {$user->points}P"
            ], 422);
        }

        // 포인트 차감 (point_logs 에 기록)
        $user->addPoints(-$totalCost, "상위노출: {$item->title} ({$days}일)", 'boost');

        // 기존 부스트가 있으면 연장, 없으면 지금부터
        $currentBoostEnd = $item->boosted_until && $item->boosted_until->isFuture()
            ? $item->boosted_until
            : now();
        $item->update([
            'boosted_until' => $currentBoostEnd->addDays($days),
            'updated_at' => now(), // 최신 게시물처럼 날짜도 갱신
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$days}일 상위노출 완료! {$totalCost}P 차감됨",
            'data' => ['boosted_until' => $item->boosted_until],
        ]);
    }
}
