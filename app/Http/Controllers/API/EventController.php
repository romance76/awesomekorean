<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Bookmark;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EventController extends Controller
{
    /**
     * GET /api/events
     * List events with category, date range, distance filter, upcoming by default
     */
    public function index(Request $request)
    {
        $query = Event::query();

        // Category filter
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // City/state filter (no 'region' column)
        if ($request->filled('city')) {
            $query->where('city', $request->city);
        }
        if ($request->filled('state')) {
            $query->where('state', $request->state);
        }

        // Date range filter – use start_date (no event_date column)
        if ($request->filled('start_date')) {
            $query->where('start_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('end_date', '<=', $request->end_date);
        }

        // By default show upcoming events
        if (!$request->filled('start_date') && !$request->filled('show_past')) {
            $query->where('start_date', '>=', now());
        }

        // Search
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%")
                  ->orWhere('venue', 'like', "%{$s}%")
                  ->orWhere('address', 'like', "%{$s}%");
            });
        }

        // Distance filter (Haversine) – events use lat/lng columns
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $radius = $request->input('radius');

        if ($lat && $lng && $radius && (float) $radius > 0) {
            $query->selectRaw(
                "*, (3959 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance",
                [(float) $lat, (float) $lng, (float) $lat]
            )->having('distance', '<', (float) $radius)
              ->orderBy('distance');
        } else {
            $query->orderBy('start_date');
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate(20),
        ]);
    }

    /**
     * GET /api/events/{id}
     * Single event with view_count, attendee_count, user attending status
     */
    public function show($id)
    {
        $event = Event::findOrFail($id);
        $event->increment('view_count');

        $isAttending = false;
        $isLiked = false;
        $isBookmarked = false;

        if (Auth::check()) {
            $isAttending = DB::table('event_attendees')
                ->where('event_id', $id)
                ->where('user_id', Auth::id())
                ->exists();
            $isLiked = DB::table('content_likes')
                ->where('user_id', Auth::id())
                ->where('likeable_type', 'event')
                ->where('likeable_id', $id)
                ->exists();
            $isBookmarked = Bookmark::where('user_id', Auth::id())
                ->where('bookmarkable_type', Event::class)
                ->where('bookmarkable_id', $id)
                ->exists();
        }

        $likeCount = DB::table('content_likes')
            ->where('likeable_type', 'event')
            ->where('likeable_id', $id)
            ->count();

        $comments = Comment::where('commentable_type', 'event')
            ->where('commentable_id', $id)
            ->with('user:id,name,nickname,avatar')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => array_merge($event->toArray(), [
                'is_attending'  => $isAttending,
                'is_liked'      => $isLiked,
                'is_bookmarked' => $isBookmarked,
                'like_count'    => $likeCount,
                'comments'      => $comments,
            ]),
        ]);
    }

    /**
     * POST /api/events
     * Create event with location data
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:100',
            'description' => 'nullable|string',
            'content'     => 'nullable|string',
            'venue'       => 'nullable|string',
            'address'     => 'nullable|string',
            'city'        => 'nullable|string',
            'state'       => 'nullable|string',
            'zipcode'     => 'nullable|string',
            'category'    => 'nullable|string',
            'price'       => 'nullable|numeric|min:0',
            'start_date'  => 'required|date|after:now',
            'end_date'    => 'nullable|date|after:start_date',
            'lat'         => 'nullable|numeric',
            'lng'         => 'nullable|numeric',
            'image'       => 'nullable|image|max:5120',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('events', 'public');
            $data['image_url'] = Storage::url($path);
        }

        // Remove 'image' key since the column is 'image_url'
        unset($data['image']);

        $event = Event::create($data);

        return response()->json([
            'success' => true,
            'message' => '이벤트가 등록되었습니다.',
            'data'    => $event,
        ], 201);
    }

    /**
     * PUT /api/events/{id}
     */
    public function update(Request $request, $id)
    {
        $event = Event::findOrFail($id);

        $event->fill($request->only([
            'title', 'description', 'content', 'venue', 'address',
            'city', 'state', 'zipcode', 'category',
            'start_date', 'end_date', 'price',
            'lat', 'lng',
        ]));
        $event->save();

        return response()->json([
            'success' => true,
            'message' => '수정되었습니다.',
            'data'    => $event,
        ]);
    }

    /**
     * DELETE /api/events/{id}
     * Delete event (no status column, just delete)
     */
    public function destroy($id)
    {
        $event = Event::findOrFail($id);
        $event->delete();

        return response()->json(['success' => true, 'message' => '이벤트가 삭제되었습니다.']);
    }

    /**
     * POST /api/events/{id}/attend
     * Toggle attendance
     */
    public function toggleAttend($id)
    {
        $event = Event::findOrFail($id);

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

            return response()->json([
                'success' => true,
                'data'    => [
                    'attending'      => false,
                    'attendee_count' => $event->fresh()->attendee_count,
                ],
            ]);
        }

        DB::table('event_attendees')->insert([
            'event_id'   => $id,
            'user_id'    => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $event->increment('attendee_count');

        // Points
        if (Auth::user() && method_exists(Auth::user(), 'addPoints')) {
            Auth::user()->addPoints(2, 'event_attend', 'earn', (int) $id, '이벤트 참가');
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'attending'      => true,
                'attendee_count' => $event->fresh()->attendee_count,
            ],
        ]);
    }

    /**
     * POST /api/events/{id}/like
     */
    public function like($id)
    {
        Event::findOrFail($id);
        $userId = Auth::id();

        $existing = DB::table('content_likes')
            ->where('user_id', $userId)
            ->where('likeable_type', 'event')
            ->where('likeable_id', $id)
            ->first();

        if ($existing) {
            DB::table('content_likes')->where('id', $existing->id)->delete();
        } else {
            DB::table('content_likes')->insert([
                'user_id'       => $userId,
                'likeable_type' => 'event',
                'likeable_id'   => $id,
                'created_at'    => now(),
            ]);
        }

        $likeCount = DB::table('content_likes')
            ->where('likeable_type', 'event')
            ->where('likeable_id', $id)
            ->count();

        return response()->json([
            'success' => true,
            'data'    => ['liked' => !$existing, 'like_count' => $likeCount],
        ]);
    }

    /**
     * POST /api/events/{id}/bookmark
     */
    public function bookmark($id)
    {
        Event::findOrFail($id);
        $userId = Auth::id();

        $existing = Bookmark::where('user_id', $userId)
            ->where('bookmarkable_type', Event::class)
            ->where('bookmarkable_id', $id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['success' => true, 'data' => ['bookmarked' => false]]);
        }

        Bookmark::create([
            'user_id'           => $userId,
            'bookmarkable_type' => Event::class,
            'bookmarkable_id'   => $id,
        ]);

        return response()->json(['success' => true, 'data' => ['bookmarked' => true]]);
    }

    /**
     * POST /api/events/{id}/comment
     */
    public function comment(Request $request, $id)
    {
        Event::findOrFail($id);
        $request->validate(['content' => 'required|string|max:2000']);

        $comment = Comment::create([
            'commentable_type' => 'event',
            'commentable_id'   => $id,
            'user_id'          => Auth::id(),
            'content'          => $request->content,
        ]);

        // Points (max 10 per day)
        $todayCount = Comment::where('user_id', Auth::id())->whereDate('created_at', today())->count();
        if ($todayCount <= 10 && Auth::user() && method_exists(Auth::user(), 'addPoints')) {
            Auth::user()->addPoints(5, 'comment_write', 'earn', $comment->id, '댓글 작성');
        }

        return response()->json([
            'success' => true,
            'message' => '댓글이 등록되었습니다.',
            'data'    => $comment->load('user:id,name,nickname,avatar'),
        ], 201);
    }
}
