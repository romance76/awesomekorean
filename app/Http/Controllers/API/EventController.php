<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Traits\CompressesUploads;
use Illuminate\Http\Request;

class EventController extends Controller
{
    use CompressesUploads;

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
            ->when(!$request->boolean('past'), fn($q) => $q->where('start_date', '>=', now()))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')), fn($q) => $q->where('is_active', true));

        // 공식 이벤트 상단 고정
        $query->orderByDesc('is_pinned');

        if ($request->lat && $request->lng) {
            $query->nearby($request->lat, $request->lng, $request->radius ?? 50)
                  ->orderBy('distance');
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
        }

        return response()->json(['success' => true, 'data' => $data]);
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
        ]);

        $fields = $request->only(
            'title', 'description', 'content', 'category', 'organizer',
            'venue', 'address', 'city', 'state', 'zipcode', 'lat', 'lng',
            'start_date', 'end_date', 'price', 'is_free', 'url', 'max_attendees'
        );
        $fields['user_id'] = auth()->id();
        $fields['is_free'] = $request->boolean('is_free');
        $fields['is_active'] = true;

        if ($request->hasFile('image')) {
            $fields['image_url'] = $this->storeCompressedImage($request->file('image'), 'events', 1400, 82);
        }

        $event = Event::create($fields);

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
        ]);

        $fields = $request->only(
            'title', 'description', 'content', 'category', 'organizer',
            'venue', 'address', 'city', 'state', 'zipcode', 'lat', 'lng',
            'start_date', 'end_date', 'price', 'is_free', 'url', 'max_attendees'
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

        return response()->json(['success' => true, 'attending' => true, 'status' => $request->status ?? 'going', 'attendee_count' => $event->fresh()->attendee_count]);
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
