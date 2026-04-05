<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GroupBuy;
use App\Models\GroupBuyParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class GroupBuyController extends Controller
{
    /**
     * GET /api/groupbuy
     * List with status filter, distance, pagination
     */
    public function index(Request $request)
    {
        $query = GroupBuy::with('user:id,name,nickname,avatar')
            ->withCount('participants')
            ->where('status', '!=', 'cancelled');

        // Category filter
        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->filled('search')) {
            $query->where('title', 'like', "%{$request->search}%");
        }

        // Distance filter (Haversine)
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $radius = $request->input('radius');

        if ($lat && $lng && $radius && (float) $radius > 0) {
            $query->selectRaw(
                "*, (3959 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                [(float) $lat, (float) $lng, (float) $lat]
            )->having('distance', '<', (float) $radius)
              ->orderBy('distance');
        } else {
            $query->latest();
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate(20),
        ]);
    }

    /**
     * GET /api/groupbuy/{id}
     * Single group buy with participants
     */
    public function show($id)
    {
        $gb = GroupBuy::with([
            'user:id,name,nickname,avatar',
            'participants.user:id,name,nickname,avatar',
        ])->withCount('participants')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $gb,
        ]);
    }

    /**
     * POST /api/groupbuy
     * Create group buy
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'            => 'required|string|max:100',
            'description'      => 'required|string|max:3000',
            'target_price'     => 'required|numeric|min:0',
            'min_participants' => 'required|integer|min:2',
            'max_participants' => 'nullable|integer|min:2',
            'category'         => 'required|string',
            'deadline'         => 'required|date|after:now',
            'product_url'      => 'nullable|url',
            'images'           => 'nullable|array|max:5',
            'images.*'         => 'image|max:5120',
        ]);

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('groupbuy', 'public');
                $images[] = Storage::url($path);
            }
        }

        $gb = GroupBuy::create([
            'user_id'          => Auth::id(),
            'title'            => $request->title,
            'description'      => $request->description,
            'target_price'     => $request->target_price,
            'min_participants' => $request->min_participants,
            'max_participants' => $request->max_participants,
            'category'         => $request->category,
            'deadline'         => $request->deadline,
            'product_url'      => $request->product_url,
            'images'           => $images,
            'status'           => 'open',
        ]);

        // Creator auto-joins
        GroupBuyParticipant::create([
            'group_buy_id' => $gb->id,
            'user_id'      => Auth::id(),
            'quantity'     => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => '공동구매가 등록되었습니다.',
            'data'    => $gb->load('user:id,name,nickname,avatar'),
        ], 201);
    }

    /**
     * POST /api/groupbuy/{id}/join
     * Join group buy, increment current_participants (check max)
     */
    public function join(Request $request, $id)
    {
        $gb = GroupBuy::findOrFail($id);

        if ($gb->status !== 'open') {
            return response()->json(['success' => false, 'message' => '모집이 종료된 공동구매입니다.'], 422);
        }

        $currentCount = $gb->participants()->count();

        if ($gb->max_participants && $currentCount >= $gb->max_participants) {
            return response()->json(['success' => false, 'message' => '최대 인원이 초과되었습니다.'], 422);
        }

        $participant = GroupBuyParticipant::firstOrCreate(
            ['group_buy_id' => $gb->id, 'user_id' => Auth::id()],
            ['quantity' => $request->input('quantity', 1)]
        );

        // Points for new participants only
        if ($participant->wasRecentlyCreated) {
            Auth::user()->addPoints(2, 'groupbuy_join', 'earn', $gb->id, '공동구매 참여');
        }

        // Auto-complete if minimum reached
        $count = $gb->participants()->count();
        if ($count >= $gb->min_participants && $gb->status === 'open') {
            $gb->update(['status' => 'completed']);
        }

        return response()->json([
            'success' => true,
            'data'    => ['joined' => true, 'participants_count' => $count],
        ]);
    }

    /**
     * POST /api/groupbuy/{id}/leave
     */
    public function leave($id)
    {
        $gb = GroupBuy::findOrFail($id);

        if ($gb->user_id === Auth::id()) {
            return response()->json(['success' => false, 'message' => '작성자는 나갈 수 없습니다.'], 422);
        }

        GroupBuyParticipant::where('group_buy_id', $id)
            ->where('user_id', Auth::id())
            ->delete();

        return response()->json([
            'success' => true,
            'data'    => ['joined' => false],
        ]);
    }

    /**
     * DELETE /api/groupbuy/{id}
     */
    public function destroy($id)
    {
        $gb = GroupBuy::findOrFail($id);

        if ($gb->user_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json(['success' => false, 'message' => '삭제 권한이 없습니다.'], 403);
        }

        $gb->update(['status' => 'cancelled']);

        return response()->json(['success' => true, 'message' => '공동구매가 취소되었습니다.']);
    }
}
