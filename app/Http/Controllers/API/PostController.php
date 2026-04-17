<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostLike;
use App\Traits\AdminAuthorizes;
use App\Traits\CompressesUploads;
use Illuminate\Http\Request;

class PostController extends Controller
{
    use AdminAuthorizes, CompressesUploads;

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

        return response()->json(['success' => true, 'data' => $post]);
    }

    public function store(Request $request)
    {
        $request->validate(['title' => 'required|max:200', 'content' => 'required', 'board_id' => 'required|exists:boards,id']);

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

        // 글 작성 포인트 +5 (Issue #12: 대상 post 연결)
        auth()->user()->addPoints(5, '게시글 작성', 'earn', ['type' => \App\Models\Post::class, 'id' => $post->id]);

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
