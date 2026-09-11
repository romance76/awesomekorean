<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostLike;
use App\Services\BadWordFilter;
use App\Traits\AdminAuthorizes;
use App\Traits\CompressesUploads;
use App\Traits\HasAdjacent;
use Illuminate\Http\Request;

class PostController extends Controller
{
    use AdminAuthorizes, CompressesUploads, HasAdjacent;

    public function index(Request $request)
    {
        $query = Post::with('user:id,name,nickname,avatar', 'board:id,name,slug')
            ->visible()
            ->when($request->board_id, fn($q, $v) => $q->where('board_id', $v))
            ->when($request->board_slug, fn($q, $v) => $q->whereHas('board', fn($b) => $b->where('slug', $v)))
            ->when($request->search, fn($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->when($request->category, fn($q, $v) => $q->where('category', $v));

        if ($request->lat && $request->lng) {
            $query->nearby($request->lat, $request->lng, $request->radius ?? 50);
        }

        $sort = $request->sort ?? 'latest';
        if ($sort === 'popular') {
            $query->orderByDesc('like_count')->orderByDesc('created_at');
        } else {
            // 최신순: 순수 시간순 (고정글도 시간순으로)
            $query->orderByDesc('created_at');
        }

        return response()->json(['success' => true, 'data' => $query->paginate($request->per_page ?? 20)]);
    }

    public function show($id)
    {
        $post = Post::with('user:id,name,nickname,avatar', 'board:id,name,slug')->findOrFail($id);

        // index()는 visible() 스코프로 숨김글을 걸러내지만 show()는 그렇지 않아,
        // 숨김(관리자 숨김 또는 작성자 본인 삭제) 처리된 글도 직접 URL로는 그대로
        // 전체 공개되던 취약점(실측 확인). 작성자 본인/관리자만 예외적으로 조회 가능.
        if ($post->is_hidden) {
            $user = auth('api')->user();
            $isOwner = $user && $user->id === $post->user_id;
            $isAdmin = $user && in_array($user->role, ['admin', 'super_admin', 'moderator'], true);
            if (!$isOwner && !$isAdmin) {
                abort(404);
            }
        }

        $post->increment('view_count');

        // 좋아요/북마크 상태
        $userId = auth('api')->id();
        if ($userId) {
            $post->is_liked = \DB::table('likes')
                ->where('likeable_type', 'App\\Models\\Post')
                ->where('likeable_id', $id)
                ->where('user_id', $userId)
                ->exists();
            $post->is_bookmarked = \DB::table('bookmarks')
                ->where('bookmarkable_type', 'App\\Models\\Post')
                ->where('bookmarkable_id', $id)
                ->where('user_id', $userId)
                ->exists();
        } else {
            $post->is_liked = false;
            $post->is_bookmarked = false;
        }

        $adj = $this->adjacentPair(Post::class, $id, 'title', ['board_id' => $post->board_id]);
        return response()->json(['success' => true, 'data' => $post, 'prev' => $adj['prev'], 'next' => $adj['next']]);
    }

    public function store(Request $request)
    {
        $request->validate(['title' => 'required|max:200', 'content' => 'required', 'board_id' => 'required|exists:boards,id']);

        $bad = BadWordFilter::firstMatch($request->title . ' ' . $request->content);
        if ($bad !== null) {
            return response()->json(['success' => false, 'message' => '부적절한 표현이 포함되어 있어 게시할 수 없습니다.'], 422);
        }

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                $images[] = $this->storeCompressedImageRaw($img, 'posts', 1200, 80);
            }
        }

        $post = Post::create([
            'board_id' => $request->board_id,
            'user_id' => auth()->id(),
            'title' => $request->title,
            'content' => $request->content,
            'category' => $request->category,
            'images' => $images ?: null,
            'lat' => $request->lat,
            'lng' => $request->lng,
            'city' => $request->city,
            'state' => $request->state,
            'zipcode' => $request->zipcode,
        ]);

        // 글 작성 포인트 (P2B-2: DB 동적, Issue #12: 대상 post 연결)
        // 작성 자체는 무제한이지만, 게시글+댓글 합산 하루 N회까지만 포인트 지급
        $amount = \App\Support\PointRules::get('post_write', 3);
        $dailyCap = \App\Support\PointRules::get('content_earn_daily_max', 3);
        $todayEarnedActions = \App\Models\PointLog::where('user_id', auth()->id())
            ->whereDate('created_at', today())
            ->whereIn('related_type', [\App\Models\Post::class, \App\Models\Comment::class])
            ->count();
        if ($amount > 0 && $todayEarnedActions < $dailyCap) {
            auth()->user()->addPoints($amount, '게시글 작성', 'earn', ['type' => \App\Models\Post::class, 'id' => $post->id]);
        }

        return response()->json(['success' => true, 'data' => $post], 201);
    }

    public function update(Request $request, $id)
    {
        $post = $this->findOwnedOrAdmin(Post::class, $id);
        $post->update($request->only('title', 'content', 'category'));
        return response()->json(['success' => true, 'data' => $post]);
    }

    public function destroy($id)
    {
        $post = $this->findOwnedOrAdmin(Post::class, $id);
        $post->update(['is_hidden' => true]);
        return response()->json(['success' => true, 'message' => '삭제되었습니다']);
    }

    public function toggleLike($id)
    {
        $post = Post::findOrFail($id);
        $existing = PostLike::where('user_id', auth()->id())->where('post_id', $id)->first();

        if ($existing) {
            $existing->delete();
            $post->decrement('like_count');
            return response()->json(['success' => true, 'liked' => false]);
        }

        PostLike::create(['user_id' => auth()->id(), 'post_id' => $id]);
        $post->increment('like_count');
        return response()->json(['success' => true, 'liked' => true]);
    }
}
