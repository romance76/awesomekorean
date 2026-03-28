<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $query = Event::whereIn('status', ['active', 'published'])
            ->with('user:id,name,username,avatar')
            ->latest('event_date');

        if ($request->region)   $query->where('region', $request->region);
        if ($request->category) $query->where('category', $request->category);

        return response()->json($query->paginate(20));
    }

    public function show($id)
    {
        $event = Event::with('user:id,name,username,avatar')->findOrFail($id);

        $isAttending = false;
        if (Auth::check()) {
            $isAttending = DB::table('event_attendees')
                ->where('event_id', $id)
                ->where('user_id', Auth::id())
                ->exists();
        }

        return response()->json(array_merge($event->toArray(), ['is_attending' => $isAttending]));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'         => 'required|string|max:100',
            'description'   => 'nullable|string',
            'location'      => 'nullable|string',
            'region'        => 'nullable|string',
            'category'      => 'in:general,meetup,food,culture,sports,education,business',
            'max_attendees' => 'nullable|integer|min:1',
            'price'         => 'nullable|numeric|min:0',
            'event_date'    => 'required|date|after:now',
            'is_online'     => 'boolean',
        ]);

        $event = Event::create([...$data, 'user_id' => Auth::id()]);
        return response()->json($event->load('user:id,name,username'), 201);
    }

    public function update(Request $request, $id)
    {
        $event = Event::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $event->update($request->only(['title','description','location','region','category','event_date','is_online','max_attendees','price']));
        return response()->json($event);
    }

    public function destroy($id)
    {
        $event = Event::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $event->update(['status' => 'cancelled']);
        return response()->json(['message' => '이벤트가 취소되었습니다.']);
    }

    public function attend($id)
    {
        $event = Event::findOrFail($id);

        if ($event->status !== 'active') {
            return response()->json(['message' => '참가할 수 없는 이벤트입니다.'], 422);
        }

        $exists = DB::table('event_attendees')
            ->where('event_id', $id)
            ->where('user_id', Auth::id())
            ->exists();

        if ($exists) {
            DB::table('event_attendees')
                ->where('event_id', $id)
                ->where('user_id', Auth::id())
                ->delete();
            $event->decrement('attendee_count');
            return response()->json(['attending' => false, 'attendee_count' => $event->fresh()->attendee_count]);
        }

        if ($event->max_attendees && $event->attendee_count >= $event->max_attendees) {
            return response()->json(['message' => '정원이 초과되었습니다.'], 422);
        }

        DB::table('event_attendees')->insert([
            'event_id'   => $id,
            'user_id'    => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $event->increment('attendee_count');

        return response()->json(['attending' => true, 'attendee_count' => $event->fresh()->attendee_count]);
    }
}
