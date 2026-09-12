<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Traits\CompressesUploads;
use App\Traits\HasAdjacent;
use Illuminate\Http\Request;

class EventController extends Controller
{
    use CompressesUploads, HasAdjacent;

    public function index(Request $request)
    {
        $query = Event::query()
            ->when($request->event_type, fn($q, $v) => $q->where('event_type', $v))
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            ->when($request->search, fn($q, $v) => $q->where(function ($q2) use ($v) {
                $q2->where('title', 'like', "%{$v}%")
                   ->orWhere('description', 'like', "%{$v}%")
                   ->orWhere('venue', 'like', "%{$v}%")
                   ->orWhere('city', 'like', "%{$v}%");
            }))
            ->when(!$request->boolean('past'), fn($q) => $q->where(function ($q2) {
                $q2->where('start_date', '>=', now())
                   ->orWhere('is_pinned', true); // 공식 이벤트는 날짜 무관 표시
            }))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')), fn($q) => $q->where('is_active', true));

        // 공식 이벤트 상단 고정
        $query->orderByDesc('is_pinned');

        if ($request->lat && $request->lng) {
            $lat = (float) $request->lat;
            $lng = (float) $request->lng;
            $radius = (int) ($request->radius ?? 50);
            // 공식 이벤트(is_pinned)는 위치 필터에서 제외하여 항상 표시
            $query->where(function ($q) use ($lat, $lng, $radius) {
                $q->where('is_pinned', true) // 공식 이벤트는 무조건 포함
                  ->orWhere(function ($q2) use ($lat, $lng, $radius) {
                      $latDelta = $radius / 69.0;
                      $lngDelta = $radius / (69.0 * cos(deg2rad($lat)));
                      $q2->whereBetween('lat', [$lat - $latDelta, $lat + $latDelta])
                         ->whereBetween('lng', [$lng - $lngDelta, $lng + $lngDelta]);
                  });
            });
            $query->orderByDesc('is_pinned')->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        return response()->json(['success' => true, 'data' => $query->paginate($request->per_page ?? 20)]);
    }

    public function show($id)
    {
        $event = Event::with('user:id,name,nickname,avatar')->findOrFail($id);
        $event->increment('view_count');

        $data = $event->toArray();
        if (auth()->check()) {
            $attendee = EventAttendee::where('event_id', $id)->where('user_id', auth()->id())->first();
            $data['my_status'] = $attendee?->status;
            $data['my_proof_status'] = $attendee?->proof_status;
        }

        $adj = $this->adjacentPair(Event::class, $id, 'title', ['category' => $event->category]);
        return response()->json(['success' => true, 'data' => $data, 'prev' => $adj['prev'], 'next' => $adj['next']]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'      => 'required|max:200',
            'start_date' => 'required|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'image'      => 'nullable|image|max:5120',
            'price'      => 'nullable|numeric|min:0',
            'max_attendees' => 'nullable|integer|min:1',
            'reward_points' => 'nullable|integer|min:0',
        ]);

        $fields = $request->only(
            'title', 'description', 'content', 'category', 'organizer',
            'venue', 'address', 'city', 'state', 'zipcode', 'lat', 'lng',
            'start_date', 'end_date', 'price', 'is_free', 'url', 'max_attendees', 'reward_points'
        );
        $fields['user_id'] = auth()->id();
        $fields['is_free'] = $request->boolean('is_free');
        $fields['is_active'] = true;

        if ($request->hasFile('image')) {
            $fields['image_url'] = $this->storeCompressedImage($request->file('image'), 'events', 1400, 82);
        }

        $event = Event::create($fields);

        \App\Support\WritePoints::award(auth()->user(), Event::class, $event->id, '이벤트 등록');

        return response()->json(['success' => true, 'data' => $event], 201);
    }

    public function update(Request $request, $id)
    {
        $event = Event::findOrFail($id);

        if ($event->user_id !== auth()->id() && !in_array(auth()->user()->role, ['admin', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'title'      => 'sometimes|required|max:200',
            'start_date' => 'sometimes|required|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'image'      => 'nullable|image|max:5120',
            'price'      => 'nullable|numeric|min:0',
            'max_attendees' => 'nullable|integer|min:1',
            'reward_points' => 'nullable|integer|min:0',
        ]);

        $fields = $request->only(
            'title', 'description', 'content', 'category', 'organizer',
            'venue', 'address', 'city', 'state', 'zipcode', 'lat', 'lng',
            'start_date', 'end_date', 'price', 'is_free', 'url', 'max_attendees', 'reward_points'
        );

        if ($request->has('is_free')) {
            $fields['is_free'] = $request->boolean('is_free');
        }

        if ($request->hasFile('image')) {
            $fields['image_url'] = $this->storeCompressedImage($request->file('image'), 'events', 1400, 82);
        }

        $event->update($fields);

        return response()->json(['success' => true, 'data' => $event->fresh()]);
    }

    public function destroy($id)
    {
        $event = Event::findOrFail($id);

        if ($event->user_id !== auth()->id() && !in_array(auth()->user()->role, ['admin', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $event->update(['is_active' => false]);

        return response()->json(['success' => true, 'message' => 'Event deactivated']);
    }

    public function toggleAttend(Request $request, $id)
    {
        $event = Event::findOrFail($id);
        $existing = EventAttendee::where('event_id', $id)->where('user_id', auth()->id())->first();

        if ($existing) {
            $existing->delete();
            $event->decrement('attendee_count');
            return response()->json(['success' => true, 'attending' => false, 'attendee_count' => $event->fresh()->attendee_count]);
        }

        if ($event->max_attendees && $event->attendee_count >= $event->max_attendees) {
            return response()->json(['success' => false, 'message' => 'Event is full'], 422);
        }

        EventAttendee::create([
            'event_id' => $id,
            'user_id'  => auth()->id(),
            'status'   => $request->status ?? 'going',
        ]);
        $event->increment('attendee_count');

        \App\Support\MilestonePoints::award(auth()->user(), 'event_join', Event::class, $event->id, "이벤트 참가: {$event->title}", 'event_join_daily_max');

        return response()->json(['success' => true, 'attending' => true, 'status' => $request->status ?? 'going', 'attendee_count' => $event->fresh()->attendee_count]);
    }

    /**
     * 완료 인증 파일 제출 — 이벤트에 보상 포인트(reward_points)가 걸려있을
     * 때만 제출 가능. 자동 지급이 아니라 관리자 확인 후 지급(사용자 결정).
     */
    public function submitProof(Request $request, $id)
    {
        $event = Event::findOrFail($id);
        if (!$event->reward_points || $event->reward_points <= 0) {
            return response()->json(['success' => false, 'message' => '이 이벤트는 완료 인증 보상이 없습니다'], 422);
        }

        $attendee = EventAttendee::where('event_id', $id)->where('user_id', auth()->id())->first();
        if (!$attendee) {
            return response()->json(['success' => false, 'message' => '참가 등록 후 제출할 수 있습니다'], 422);
        }
        if ($attendee->proof_status === 'pending' || $attendee->proof_status === 'approved') {
            return response()->json(['success' => false, 'message' => '이미 제출했습니다'], 422);
        }

        $request->validate(['file' => 'required|file|max:10240']);
        $path = $this->storeDocument($request->file('file'), 'event_proofs');
        $attendee->update(['proof_file' => $path, 'proof_status' => 'pending', 'reviewed_at' => null]);

        return response()->json(['success' => true, 'message' => '제출되었습니다. 관리자 확인 후 보상이 지급됩니다.', 'data' => $attendee->fresh()]);
    }

    public function attendees($id)
    {
        $event = Event::findOrFail($id);
        $attendees = EventAttendee::where('event_id', $id)
            ->with('user:id,name,nickname,avatar')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($a) => [
                'id'         => $a->id,
                'user_id'    => $a->user_id,
                'status'     => $a->status,
                'user'       => $a->user,
                'created_at' => $a->created_at,
            ]);

        return response()->json(['success' => true, 'data' => $attendees]);
    }
}
