<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Sweepstakes;
use App\Models\SweepstakesEntry;
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
                   ->orWhere('is_pinned', true) // 공식 이벤트는 날짜 무관 표시
                   ->orWhere('event_type', 'sweepstakes'); // 경품 추첨은 응모 종료일까지 노출(상태로 관리)
            }))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')), fn($q) => $q->where('is_active', true));

        // 공식 이벤트 상단 고정
        $query->orderByDesc('is_pinned');

        if ($request->lat && $request->lng) {
            $lat = (float) $request->lat;
            $lng = (float) $request->lng;
            $radius = (int) ($request->radius ?? 50);
            // 공식 이벤트(is_pinned)와 경품 추첨은 위치 필터에서 제외하여 항상 표시
            $query->where(function ($q) use ($lat, $lng, $radius) {
                $q->where('is_pinned', true) // 공식 이벤트는 무조건 포함
                  ->orWhere('event_type', 'sweepstakes')
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

        if ($event->event_type === 'sweepstakes') {
            $sweepstakes = Sweepstakes::where('event_id', $event->id)->first();
            if ($sweepstakes) {
                $myEntries = 0;
                if (auth()->check()) {
                    $myEntries = (int) (SweepstakesEntry::where('sweepstakes_id', $sweepstakes->id)
                        ->where('user_id', auth()->id())
                        ->value('entries_count') ?? 0);
                }
                $total = (int) $sweepstakes->total_entries;
                $probability = $total > 0 ? round(($myEntries / $total) * 100, 2) : 0.0;
                $winnerName = null;
                if ($sweepstakes->status === 'winner_selected' && $sweepstakes->winner_user_id) {
                    $winnerName = $sweepstakes->winner?->display_name;
                }

                $breakdownQuery = SweepstakesEntry::where('sweepstakes_id', $sweepstakes->id);
                if (auth()->check()) {
                    $breakdownQuery->where('user_id', '!=', auth()->id());
                }
                $otherEntriesBreakdown = $breakdownQuery->orderByDesc('entries_count')->limit(100)->pluck('entries_count');

                $data['sweepstakes'] = array_merge($sweepstakes->toArray(), [
                    'my_entries' => $myEntries,
                    'my_win_probability_pct' => $probability,
                    'winner_display_name' => $winnerName,
                    'other_entries_breakdown' => $otherEntriesBreakdown,
                ]);
            }
        }

        $adj = $this->adjacentPair(Event::class, $id, 'title', ['category' => $event->category]);
        return response()->json(['success' => true, 'data' => $data, 'prev' => $adj['prev'], 'next' => $adj['next']]);
    }

    public function store(Request $request)
    {
        $isSweepstakes = $request->event_type === 'sweepstakes';
        if ($isSweepstakes && auth()->user()->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => '경품 추첨 이벤트는 사이트 최고관리자만 등록할 수 있습니다'], 403);
        }

        $request->validate([
            'title'      => 'required|max:200',
            'start_date' => 'required|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'image'      => 'nullable|image|max:5120',
            'price'      => 'nullable|numeric|min:0',
            'max_attendees' => 'nullable|integer|min:1',
            'reward_points' => 'nullable|integer|min:0',
            'prize_name' => $isSweepstakes ? 'required|string|max:255' : 'nullable|string|max:255',
            'prize_value' => 'nullable|numeric|min:0',
            'minimum_age' => 'nullable|integer|min:0|max:120',
            'eligible_regions' => 'nullable|array',
            'official_rules_url' => 'nullable|string|max:255',
            'no_purchase_required_text' => 'nullable|string',
        ]);

        $fields = $request->only(
            'title', 'description', 'content', 'category', 'organizer',
            'venue', 'address', 'city', 'state', 'zipcode', 'lat', 'lng',
            'start_date', 'end_date', 'price', 'is_free', 'url', 'max_attendees', 'reward_points'
        );
        $fields['user_id'] = auth()->id();
        $fields['is_free'] = $request->boolean('is_free');
        $fields['is_active'] = true;
        $fields['event_type'] = $isSweepstakes ? 'sweepstakes' : 'user';

        if ($request->hasFile('image')) {
            $fields['image_url'] = $this->storeCompressedImage($request->file('image'), 'events', 1400, 82);
        }

        $event = Event::create($fields);

        if ($isSweepstakes) {
            Sweepstakes::create([
                'event_id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'prize_name' => $request->prize_name,
                'prize_value' => $request->prize_value,
                'prize_image' => $event->image_url,
                'start_at' => $event->start_date,
                'end_at' => $event->end_date ?? $event->start_date,
                'status' => 'active',
                'minimum_age' => $request->minimum_age ?? 18,
                'eligible_regions' => $request->eligible_regions,
                'official_rules_url' => $request->official_rules_url,
                'no_purchase_required_text' => $request->no_purchase_required_text,
            ]);
        } else {
            \App\Support\WritePoints::award(auth()->user(), Event::class, $event->id, '이벤트 등록');
        }

        return response()->json(['success' => true, 'data' => $event], 201);
    }

    public function update(Request $request, $id)
    {
        $event = Event::findOrFail($id);

        if ($event->user_id !== auth()->id() && !in_array(auth()->user()->role, ['admin', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $sweepstakes = $event->event_type === 'sweepstakes' ? Sweepstakes::where('event_id', $event->id)->first() : null;
        if ($sweepstakes && auth()->user()->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => '경품 추첨 이벤트는 사이트 최고관리자만 수정할 수 있습니다'], 403);
        }
        if ($sweepstakes && $sweepstakes->status === 'winner_selected') {
            return response()->json(['success' => false, 'message' => '당첨자가 이미 선정된 경품 추첨 이벤트는 수정할 수 없습니다'], 422);
        }

        $request->validate([
            'title'      => 'sometimes|required|max:200',
            'start_date' => 'sometimes|required|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'image'      => 'nullable|image|max:5120',
            'price'      => 'nullable|numeric|min:0',
            'max_attendees' => 'nullable|integer|min:1',
            'reward_points' => 'nullable|integer|min:0',
            'prize_name' => $sweepstakes ? 'sometimes|required|string|max:255' : 'nullable|string|max:255',
            'prize_value' => 'nullable|numeric|min:0',
            'minimum_age' => 'nullable|integer|min:0|max:120',
            'eligible_regions' => 'nullable|array',
            'official_rules_url' => 'nullable|string|max:255',
            'no_purchase_required_text' => 'nullable|string',
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

        if ($sweepstakes) {
            $sweepstakes->forceFill([
                'title' => $event->title,
                'description' => $event->description,
                'prize_name' => $request->prize_name ?? $sweepstakes->prize_name,
                'prize_value' => $request->has('prize_value') ? $request->prize_value : $sweepstakes->prize_value,
                'start_at' => $event->start_date,
                'end_at' => $event->end_date ?? $event->start_date,
                'minimum_age' => $request->minimum_age ?? $sweepstakes->minimum_age,
                'eligible_regions' => $request->has('eligible_regions') ? $request->eligible_regions : $sweepstakes->eligible_regions,
                'official_rules_url' => $request->official_rules_url ?? $sweepstakes->official_rules_url,
                'no_purchase_required_text' => $request->no_purchase_required_text ?? $sweepstakes->no_purchase_required_text,
            ])->save();
        }

        return response()->json(['success' => true, 'data' => $event->fresh()]);
    }

    public function destroy($id)
    {
        $event = Event::findOrFail($id);

        if ($event->event_type === 'sweepstakes') {
            if (auth()->user()->role !== 'super_admin') {
                return response()->json(['success' => false, 'message' => '경품 추첨 이벤트는 사이트 최고관리자만 삭제할 수 있습니다'], 403);
            }
        } elseif ($event->user_id !== auth()->id() && !in_array(auth()->user()->role, ['admin', 'super_admin'])) {
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
