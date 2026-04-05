<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAttendee;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $query = Event::query()
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            ->when($request->search, fn($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->when(!$request->past, fn($q) => $q->where('start_date', '>=', now()));

        if ($request->lat && $request->lng) {
            $query->nearby($request->lat, $request->lng, $request->radius ?? 50);
        } else {
            $query->orderBy('start_date');
        }

        return response()->json(['success' => true, 'data' => $query->paginate(20)]);
    }

    public function show($id)
    {
        $event = Event::findOrFail($id);
        $event->increment('view_count');
        return response()->json(['success' => true, 'data' => $event]);
    }

    public function store(Request $request)
    {
        $request->validate(['title' => 'required|max:200', 'start_date' => 'required|date']);

        $event = Event::create($request->only(
            'title', 'description', 'content', 'image_url', 'category', 'organizer',
            'venue', 'address', 'city', 'state', 'zipcode', 'lat', 'lng',
            'start_date', 'end_date', 'price', 'url'
        ));

        return response()->json(['success' => true, 'data' => $event], 201);
    }

    public function toggleAttend(Request $request, $id)
    {
        $event = Event::findOrFail($id);
        $existing = EventAttendee::where('event_id', $id)->where('user_id', auth()->id())->first();

        if ($existing) {
            $existing->delete();
            $event->decrement('attendee_count');
            return response()->json(['success' => true, 'attending' => false]);
        }

        EventAttendee::create(['event_id' => $id, 'user_id' => auth()->id(), 'status' => $request->status ?? 'going']);
        $event->increment('attendee_count');
        return response()->json(['success' => true, 'attending' => true]);
    }
}
