<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GroupBuy;
use App\Models\GroupBuyParticipant;
use App\Traits\AdminAuthorizes;
use App\Traits\CompressesUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GroupBuyController extends Controller
{
    use AdminAuthorizes, CompressesUploads;

    public function index(Request $request)
    {
        $query = GroupBuy::with('user:id,name,nickname,avatar')
            ->approved()
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            ->when($request->search, fn($q, $v) => $q->where(function($q2) use ($v) {
                $q2->where('title', 'like', "%{$v}%")->orWhere('content', 'like', "%{$v}%");
            }));

        if ($request->lat && $request->lng) {
            $query->nearby($request->lat, $request->lng, $request->radius ?? 50)
                ->orderBy('distance');
        } else {
            $query->orderByDesc('created_at');
        }

        $items = $query->paginate($request->per_page ?? 20);
        $items->getCollection()->transform(function($item) {
            $item->append(['current_discount', 'current_price']);
            return $item;
        });

        return response()->json(['success' => true, 'data' => $items]);
    }

    /**
     * 내가 참여한 공동구매 ID 목록
     */
    public function myJoined()
    {
        $ids = GroupBuyParticipant::where('user_id', auth()->id())
            ->whereIn('status', ['pending', 'paid'])
            ->pluck('group_buy_id')
            ->toArray();

        return response()->json(['success' => true, 'data' => $ids]);
    }

    public function show($id)
    {
        $gb = GroupBuy::with(['user:id,name,nickname,avatar', 'participants' => function($q) {
            $q->where('status', '!=', 'cancelled')->with('user:id,name,nickname,avatar');
        }])->findOrFail($id);

        $gb->append(['current_discount', 'current_price']);

        $progress = $gb->max_participants
            ? round(($gb->current_participants / $gb->max_participants) * 100, 1)
            : round(($gb->current_participants / $gb->min_participants) * 100, 1);

        $timeRemaining = null;
        if ($gb->deadline && $gb->deadline->isFuture()) {
            $timeRemaining = now()->diff($gb->deadline)->format('%dd %hh %im');
        }

        $nextTier = null;
        if ($gb->discount_tiers) {
            $next = collect($gb->discount_tiers)
                ->sortBy('min_people')
                ->first(fn($t) => $t['min_people'] > $gb->current_participants);
            if ($next) {
                $nextTier = [
                    'people_needed' => $next['min_people'] - $gb->current_participants,
                    'discount_pct' => $next['discount_pct'],
                ];
            }
        }

        // 내 참여 정보
        $myParticipation = null;
        if (auth('api')->check()) {
            $myParticipation = $gb->participants
                ->where('user_id', auth('api')->id())
                ->whereIn('status', ['pending', 'paid'])
                ->first();
        }

        return response()->json(['success' => true, 'data' => array_merge($gb->toArray(), [
            'progress' => min($progress, 100),
            'time_remaining' => $timeRemaining,
            'next_tier' => $nextTier,
            'participants_count' => $gb->participants->count(),
            'my_participation' => $myParticipation,
        ])]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'original_price' => 'required|numeric|min:0',
            'min_participants' => 'required|integer|min:2',
            'max_participants' => 'nullable|integer|min:2',
            'category' => 'required|string',
            'end_type' => 'required|in:target_met,time_limit,flexible',
            'payment_method' => 'required|in:point,stripe,both,none',
            'images.*' => 'nullable|image|max:5120',
            'business_doc' => 'nullable|file|max:10240',
            'discount_tiers' => 'nullable|json',
            'deadline' => 'nullable|date|after:now',
        ]);

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $images[] = $this->storeCompressedImageRaw($file, 'groupbuys', 1200, 80);
            }
        }

        $businessDoc = null;
        if ($request->hasFile('business_doc')) {
            $businessDoc = $request->file('business_doc')->store('groupbuys/docs', 'public');
        }

        $gb = GroupBuy::create([
            'user_id' => auth()->id(),
            'title' => $request->title,
            'content' => $request->content,
            'images' => $images,
            'product_url' => $request->product_url,
            'business_doc' => $businessDoc,
            'original_price' => $request->original_price,
            'group_price' => $request->group_price,
            'discount_tiers' => $request->discount_tiers ? json_decode($request->discount_tiers, true) : null,
            'min_participants' => $request->min_participants,
            'max_participants' => $request->max_participants,
            'current_participants' => 0,
            'lat' => $request->lat,
            'lng' => $request->lng,
            'city' => $request->city,
            'state' => $request->state,
            'status' => 'recruiting',
            'end_type' => $request->end_type,
            'payment_method' => $request->payment_method,
            'is_approved' => false,
            'category' => $request->category,
            'deadline' => $request->deadline,
        ]);

        return response()->json(['success' => true, 'data' => $gb->load('user:id,name,nickname')], 201);
    }

    public function update(Request $request, $id)
    {
        $gb = $this->findOwnedOrAdmin(GroupBuy::class, $id);

        if ($gb->is_approved) {
            return response()->json(['success' => false, 'message' => '승인된 공동구매는 수정할 수 없습니다.'], 403);
        }

        $request->validate([
            'title' => 'sometimes|string|max:200',
            'content' => 'sometimes|string',
            'original_price' => 'sometimes|numeric|min:0',
            'min_participants' => 'sometimes|integer|min:2',
            'images.*' => 'nullable|image|max:5120',
            'business_doc' => 'nullable|file|max:10240',
            'discount_tiers' => 'nullable|json',
            'deadline' => 'nullable|date|after:now',
        ]);

        $data = $request->only([
            'title', 'content', 'product_url', 'original_price', 'group_price',
            'min_participants', 'max_participants', 'lat', 'lng', 'city', 'state',
            'end_type', 'payment_method', 'category', 'deadline',
        ]);

        if ($request->discount_tiers) {
            $data['discount_tiers'] = json_decode($request->discount_tiers, true);
        }

        if ($request->hasFile('images')) {
            if ($gb->images) {
                foreach ($gb->images as $old) {
                    Storage::disk('public')->delete($old);
                }
            }
            $images = [];
            foreach ($request->file('images') as $file) {
                $images[] = $this->storeCompressedImageRaw($file, 'groupbuys', 1200, 80);
            }
            $data['images'] = $images;
        }

        if ($request->hasFile('business_doc')) {
            if ($gb->business_doc) {
                Storage::disk('public')->delete($gb->business_doc);
            }
            $data['business_doc'] = $request->file('business_doc')->store('groupbuys/docs', 'public');
        }

        $gb->update(array_filter($data, fn($v) => $v !== null));

        return response()->json(['success' => true, 'data' => $gb->fresh()->load('user:id,name,nickname')]);
    }

    public function destroy($id)
    {
        $gb = $this->findOwnedOrAdmin(GroupBuy::class, $id);

        if ($gb->status === 'confirmed') {
            return response()->json(['success' => false, 'message' => '확정된 공동구매는 삭제할 수 없습니다.'], 403);
        }

        // Refund all paid participants
        $paidParticipants = $gb->participants()->where('status', 'paid')->get();
        foreach ($paidParticipants as $p) {
            if ($p->payment_type === 'point' && $p->paid_amount > 0) {
                $p->user->addPoints($p->paid_amount, "공동구매 취소 환불: {$gb->title}", 'groupbuy_refund', ['type' => \App\Models\GroupBuy::class, 'id' => $gb->id]);
            }
            $p->update(['status' => 'refunded']);
        }

        if ($gb->images) {
            foreach ($gb->images as $img) {
                Storage::disk('public')->delete($img);
            }
        }
        if ($gb->business_doc) {
            Storage::disk('public')->delete($gb->business_doc);
        }

        $gb->update(['status' => 'cancelled']);
        $gb->delete();

        return response()->json(['success' => true, 'message' => '공동구매가 삭제되었습니다.']);
    }

    public function join(Request $request, $id)
    {
        $gb = GroupBuy::findOrFail($id);
        $user = auth()->user();

        if (!$gb->is_approved) {
            return response()->json(['success' => false, 'message' => '아직 승인되지 않은 공동구매입니다.'], 403);
        }
        if ($gb->status !== 'recruiting') {
            return response()->json(['success' => false, 'message' => '모집 중인 공동구매가 아닙니다.'], 400);
        }
        if ($gb->user_id === $user->id) {
            return response()->json(['success' => false, 'message' => '본인이 개설한 공동구매에 참여할 수 없습니다.'], 400);
        }
        if ($gb->deadline && $gb->deadline->isPast()) {
            return response()->json(['success' => false, 'message' => '마감 기한이 지났습니다.'], 400);
        }
        $existing = GroupBuyParticipant::where('group_buy_id', $id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'paid'])
            ->first();
        if ($existing) {
            return response()->json(['success' => false, 'message' => '이미 참여한 공동구매입니다.'], 400);
        }
        if ($gb->max_participants && $gb->current_participants >= $gb->max_participants) {
            return response()->json(['success' => false, 'message' => '정원이 초과되었습니다.'], 400);
        }

        $quantity = max(1, (int) $request->input('quantity', 1));
        // $1 = 100P
        $amount = $gb->current_price * $quantity * 100;
        $paymentType = 'none';
        $paymentId = null;
        $status = 'pending';

        return DB::transaction(function() use ($gb, $user, $request, $quantity, &$amount, &$paymentType, &$paymentId, &$status) {
            switch ($gb->payment_method) {
                case 'none':
                    $paymentType = 'none';
                    $amount = 0;
                    $status = 'paid';
                    break;

                case 'point':
                    if ($user->points < $amount) {
                        return response()->json(['success' => false, 'message' => '포인트가 부족합니다.'], 400);
                    }
                    $user->addPoints(-$amount, "공동구매 참여: {$gb->title}", 'groupbuy_join', ['type' => \App\Models\GroupBuy::class, 'id' => $gb->id]);
                    $paymentType = 'point';
                    $status = 'paid';
                    break;

                case 'stripe':
                    $paymentType = 'stripe';
                    $paymentId = $request->input('payment_id');
                    $status = $paymentId ? 'paid' : 'pending';
                    break;

                case 'both':
                    $paymentType = $request->input('payment_type', 'point');
                    if ($paymentType === 'point') {
                        if ($user->points < $amount) {
                            return response()->json(['success' => false, 'message' => '포인트가 부족합니다.'], 400);
                        }
                        $user->addPoints(-$amount, "공동구매 참여: {$gb->title}", 'groupbuy_join', ['type' => \App\Models\GroupBuy::class, 'id' => $gb->id]);
                        $status = 'paid';
                    } else {
                        $paymentId = $request->input('payment_id');
                        $status = $paymentId ? 'paid' : 'pending';
                    }
                    break;
            }

            $participant = GroupBuyParticipant::create([
                'group_buy_id' => $gb->id,
                'user_id' => $user->id,
                'quantity' => $quantity,
                'paid_amount' => $amount,
                'payment_type' => $paymentType,
                'payment_id' => $paymentId,
                'status' => $status,
            ]);

            $gb->increment('current_participants');

            if ($gb->end_type === 'target_met' && $gb->max_participants && ($gb->current_participants + 1) >= $gb->max_participants) {
                $gb->update(['status' => 'confirmed']);
            }

            return response()->json(['success' => true, 'data' => $participant->load('user:id,name,nickname')], 201);
        });
    }

    public function cancelParticipation($id)
    {
        $gb = GroupBuy::findOrFail($id);
        $user = auth()->user();

        $participant = GroupBuyParticipant::where('group_buy_id', $id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'paid'])
            ->firstOrFail();

        if ($gb->status === 'confirmed') {
            return response()->json(['success' => false, 'message' => '확정된 공동구매는 취소할 수 없습니다.'], 400);
        }

        DB::transaction(function() use ($gb, $user, $participant) {
            if ($participant->status === 'paid' && $participant->paid_amount > 0) {
                if ($participant->payment_type === 'point') {
                    $user->addPoints($participant->paid_amount, "공동구매 참여 취소 환불: {$gb->title}", 'groupbuy_refund', ['type' => \App\Models\GroupBuy::class, 'id' => $gb->id]);
                }
            }

            $participant->update(['status' => 'cancelled']);
            $gb->decrement('current_participants');
        });

        return response()->json(['success' => true, 'message' => '참여가 취소되었습니다.']);
    }

    public function participants($id)
    {
        $gb = GroupBuy::findOrFail($id);

        $participants = $gb->participants()
            ->with('user:id,name,nickname,avatar')
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $participants]);
    }

    // --- Admin Methods ---

    public function adminApprove(Request $request, $id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => '권한이 없습니다.'], 403);
        }

        $gb = GroupBuy::findOrFail($id);
        $gb->update([
            'is_approved' => true,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        return response()->json(['success' => true, 'data' => $gb->fresh()->load('user:id,name,nickname')]);
    }

    public function adminReject(Request $request, $id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => '권한이 없습니다.'], 403);
        }

        $request->validate(['rejection_reason' => 'required|string|max:500']);

        $gb = GroupBuy::findOrFail($id);
        $gb->update([
            'is_approved' => false,
            'rejection_reason' => $request->rejection_reason,
        ]);

        return response()->json(['success' => true, 'data' => $gb->fresh()->load('user:id,name,nickname')]);
    }

    public function adminComplete($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => '권한이 없습니다.'], 403);
        }

        $gb = GroupBuy::findOrFail($id);

        if (!in_array($gb->status, ['recruiting', 'confirmed'])) {
            return response()->json(['success' => false, 'message' => '완료 처리할 수 없는 상태입니다.'], 400);
        }

        $gb->update(['status' => 'completed']);

        return response()->json(['success' => true, 'data' => $gb->fresh()->load('user:id,name,nickname')]);
    }
}
