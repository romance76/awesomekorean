<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MarketItem;
use App\Models\MarketReservation;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class MarketController extends Controller
{
    public function index(Request $request)
    {
        $query = MarketItem::with('user:id,name,nickname')
            ->whereIn('status', ['active', 'reserved'])
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            ->when($request->condition, fn($q, $v) => $q->where('condition', $v))
            ->when($request->search, fn($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->when($request->min_price, fn($q, $v) => $q->where('price', '>=', $v))
            ->when($request->max_price, fn($q, $v) => $q->where('price', '<=', $v));

        if ($request->lat && $request->lng) {
            $query->nearby($request->lat, $request->lng, $request->radius ?? 10);
        } else {
            // 부스트된 아이템 먼저, 그 다음 최신순
            $query->orderByRaw('CASE WHEN boosted_until > NOW() THEN 0 ELSE 1 END')
                  ->orderByDesc('created_at');
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

        $request->validate([
            'title' => 'required|max:200',
            'content' => 'required',
            'price' => 'required|numeric|min:0',
            'category' => 'required',
            'condition' => 'required',
            'hold_enabled' => 'nullable|boolean',
            'hold_price_per_6h' => 'nullable|integer|min:0',
            'hold_max_hours' => 'nullable|integer|min:6|max:168',
            'images.*' => 'nullable|image|max:10240',
        ]);

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                $images[] = $img->store('market', 'public');
            }
        }

        $item = MarketItem::create(array_merge(
            $request->only('title', 'content', 'price', 'category', 'condition', 'lat', 'lng', 'city', 'state', 'is_negotiable', 'hold_enabled', 'hold_price_per_6h', 'hold_max_hours'),
            ['user_id' => auth()->id(), 'images' => $images ?: null]
        ));

        return response()->json(['success' => true, 'data' => $item], 201);
    }

    public function update(Request $request, $id)
    {
        $item = MarketItem::where('user_id', auth()->id())->findOrFail($id);
        $item->update($request->only('title', 'content', 'price', 'category', 'condition', 'status', 'is_negotiable', 'hold_enabled', 'hold_price_per_6h', 'hold_max_hours'));
        return response()->json(['success' => true, 'data' => $item]);
    }

    public function destroy($id)
    {
        MarketItem::where('user_id', auth()->id())->findOrFail($id)->delete();
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

        // 포인트 차감/지급
        $buyer->decrement('points', $totalCost);
        User::find($item->user_id)?->increment('points', $sellerReceived);
        // 수수료는 시스템 (운영자 계정 ID=1 등에 적립하거나 별도 관리)

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

        // 포인트 차감
        $user->decrement('points', $totalCost);

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
