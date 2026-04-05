<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Post;
use App\Models\PostLike;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    /**
     * GET /api/posts
     */
    public function index(Request $request)
    {
        $query = Post::with(['user:id,name,nickname,avatar', 'board:id,name,slug'])
            ->where('is_hidden', false);

        // Filter by board
        if ($request->board_id) {
            $query->where('board_id', $request->board_id);
        } elseif ($request->board) {
            $board = Board::where('slug', $request->board)->first();
            if ($board) {
                $query->where('board_id', $board->id);
            }
        }

        // Filter by category
        if ($request->category) {
            $query->where('category', $request->category);
        }

        // Search
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Distance filter – posts use lat/lng columns
        if ($request->lat && $request->lng) {
            $lat = (float) $request->lat;
            $lng = (float) $request->lng;
            $radius = (float) ($request->radius ?? 30);
            $query->selectRaw("posts.*, (3959 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance", [$lat, $lng, $lat])
                  ->having('distance', '<=', $radius)
                  ->orderBy('distance');
        }

        // Sort
        if ($request->sort === 'views') {
            $query->orderByDesc('view_count');
        } else {
            $query->orderByDesc('is_pinned')->orderByDesc('created_at');
        }

        $perPage = min((int) ($request->per_page ?? 20), 50);

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($perPage),
        ]);
    }

    /**
     * GET /api/posts/{id}
     */
    public function show($id)
    {
        $post = Post::with([
            'user:id,name,nickname,avatar',
            'board:id,name,slug',
        ])->findOrFail($id);

        // Use is_hidden instead of status
        if ($post->is_hidden) {
            return response()->json(['success' => false, 'message' => '삭제된 게시글입니다.'], 404);
        }

        $post->increment('view_count');

        $data = $post->toArray();

        // Check if current user liked
        if (auth()->check()) {
            $data['is_liked'] = PostLike::where('user_id', auth()->id())
                ->where('post_id', $post->id)
                ->exists();
        } else {
            $data['is_liked'] = false;
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * POST /api/posts
     */
    public function store(Request $request)
    {
        $request->validate([
            'board_id' => 'required|exists:boards,id',
            'title'    => 'required|string|max:200',
            'content'  => 'required|string',
            'images'   => 'nullable|array|max:5',
            'images.*' => 'image|max:5120',
        ]);

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $images[] = $image->store('posts', 'public');
            }
        }

        $post = Post::create([
            'board_id'  => $request->board_id,
            'user_id'   => auth()->id(),
            'title'     => $request->title,
            'content'   => $request->content,
            'images'    => !empty($images) ? json_encode($images) : null,
            'category'  => $request->category,
        ]);

        return response()->json([
            'success' => true,
            'message' => '게시글이 등록되었습니다.',
            'data'    => $post->load(['user:id,name,nickname,avatar', 'board:id,name,slug']),
        ], 201);
    }

    /**
     * PUT /api/posts/{id}
     */
    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);

        if ($post->user_id !== auth()->id() && !(auth()->user() && auth()->user()->role === 'admin')) {
            return response()->json(['success' => false, 'message' => '수정 권한이 없습니다.'], 403);
        }

        $request->validate([
            'title'   => 'sometimes|string|max:200',
            'content' => 'sometimes|string',
        ]);

        $post->update($request->only(['title', 'content']));

        return response()->json([
            'success' => true,
            'message' => '수정되었습니다.',
            'data'    => $post->fresh(),
        ]);
    }

    /**
     * DELETE /api/posts/{id}
     * Hide post using is_hidden column (no status column)
     */
    public function destroy($id)
    {
        $post = Post::findOrFail($id);

        if ($post->user_id !== auth()->id() && !(auth()->user() && auth()->user()->role === 'admin')) {
            return response()->json(['success' => false, 'message' => '삭제 권한이 없습니다.'], 403);
        }

        $post->update(['is_hidden' => true]);
        $post->delete();

        return response()->json([
            'success' => true,
            'message' => '삭제되었습니다.',
        ]);
    }

    /**
     * POST /api/posts/{id}/like
     */
    public function toggleLike($id)
    {
        $post = Post::findOrFail($id);
        $userId = auth()->id();

        $existing = PostLike::where('user_id', $userId)->where('post_id', $post->id)->first();

        if ($existing) {
            $existing->delete();
            $post->decrement('like_count');
            return response()->json([
                'success' => true,
                'data'    => ['liked' => false, 'like_count' => $post->fresh()->like_count],
            ]);
        }

        PostLike::create(['user_id' => $userId, 'post_id' => $post->id]);
        $post->increment('like_count');

        return response()->json([
            'success' => true,
            'data'    => ['liked' => true, 'like_count' => $post->fresh()->like_count],
        ]);
    }
}
