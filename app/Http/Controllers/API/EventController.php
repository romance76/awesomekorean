<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Bookmark;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\HasDistanceFilter;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    use HasDistanceFilter;
    public function index(Request $request)
    {
        $query = Event::whereNotIn('status', ['cancelled', 'draft'])
            ->with('user:id,name,username,avatar')
            ->latest('event_date');

        if ($request->region)   $query->where('region', $request->region);
        if ($request->category) $query->where('category', $request->category);

        if ($request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%")
                  ->orWhere('location', 'like', "%{$s}%");
            });
        }

        $this->applyDistanceFilter($query, $request, "latitude", "longitude");
        return response()->json($query->paginate(20));
    }

    public function show($id)
    {
        $event = Event::with('user:id,name,username,avatar')->findOrFail($id);

        $isAttending = false;
        $isLiked     = false;
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

        // 좋아요 수 (content_likes 테이블에서 집계)
        $likeCount = DB::table('content_likes')
            ->where('likeable_type', 'event')
            ->where('likeable_id', $id)
            ->count();

        // 댓글 불러오기
        $comments = Comment::where('commentable_type', 'event')
            ->where('commentable_id', $id)
            ->with('user:id,name,username,avatar')
            ->latest()
            ->get();

        return response()->json(array_merge($event->toArray(), [
            'is_attending'  => $isAttending,
            'is_liked'      => $isLiked,
            'is_bookmarked' => $isBookmarked,
            'like_count'    => $likeCount,
            'comments'      => $comments,
        ]));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'         => 'required|string|max:100',
            'description'   => 'nullable|string',
            'location'      => 'nullable|string',
            'region'        => 'nullable|string',
            'category'      => 'nullable|in:general,meetup,food,culture,sports,education,business,social',
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
        $event = Event::findOrFail($id);
        if ($event->user_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json(['message' => '수정 권한이 없습니다.'], 403);
        }
        $event->update($request->only(['title','description','location','region','category','event_date','is_online','max_attendees','price']));
        return response()->json($event);
    }

    public function destroy($id)
    {
        $event = Event::findOrFail($id);
        if ($event->user_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json(['message' => '삭제 권한이 없습니다.'], 403);
        }
        $event->update(['status' => 'cancelled']);
        return response()->json(['message' => '이벤트가 취소되었습니다.']);
    }

    public function attend($id)
    {
        $event = Event::findOrFail($id);

        if (in_array($event->status, ['cancelled', 'draft'])) {
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

        // 포인트 적립: 이벤트 참가
        Auth::user()->addPoints(2, 'event_attend', 'earn', (int)$id, '이벤트 참가');

        return response()->json(['attending' => true, 'attendee_count' => $event->fresh()->attendee_count]);
    }

    /** 좋아요 토글 */
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

        return response()->json(['liked' => !$existing, 'like_count' => $likeCount]);
    }

    /** 북마크 토글 */
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
            return response()->json(['bookmarked' => false]);
        }

        Bookmark::create([
            'user_id'           => $userId,
            'bookmarkable_type' => Event::class,
            'bookmarkable_id'   => $id,
        ]);
        return response()->json(['bookmarked' => true]);
    }

    /** 댓글 작성 */
    public function comment(Request $request, $id)
    {
        Event::findOrFail($id);
        $request->validate(['content' => 'required|string|max:2000']);

        $comment = Comment::create([
            'post_id'          => null,
            'commentable_type' => 'event',
            'commentable_id'   => $id,
            'user_id'          => Auth::id(),
            'content'          => $request->content,
        ]);

        // 포인트 지급
        $todayCount = Comment::where('user_id', Auth::id())->whereDate('created_at', today())->count();
        if ($todayCount <= 10) {
            Auth::user()->addPoints(5, 'comment_write', 'earn', $comment->id, '댓글 작성');
        }

        return response()->json([
            'message' => '댓글이 등록되었습니다.',
            'comment' => $comment->load('user:id,name,username,avatar'),
        ], 201);
    }
}
