<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Short;
use App\Models\ShortLike;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShortController extends Controller
{
    /**
     * GET /api/shorts
     * List shorts, random/newest sort, infinite scroll pagination
     */
    public function index(Request $request)
    {
        $perPage = min((int) ($request->per_page ?? 20), 500);
        $user = Auth::user();

        $query = Short::with('user:id,nickname,avatar')
            ->where('is_active', true);

        // Search
        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Sort
        $sort = $request->input('sort', 'newest');
        if ($sort === 'random') {
            $query->inRandomOrder();
        } else {
            $query->latest();
        }

        $shorts = $query->paginate($perPage);

        // Append liked status
        if ($user) {
            $shortIds = collect($shorts->items())->pluck('id')->toArray();
            $likedIds = ShortLike::where('user_id', $user->id)
                ->whereIn('short_id', $shortIds)
                ->pluck('short_id')
                ->toArray();

            $shorts->getCollection()->transform(function ($s) use ($likedIds) {
                $s->liked = in_array($s->id, $likedIds);
                return $s;
            });
        } else {
            $shorts->getCollection()->transform(function ($s) {
                $s->liked = false;
                return $s;
            });
        }

        return response()->json([
            'success' => true,
            'data'    => $shorts,
        ]);
    }

    /**
     * GET /api/shorts/{id}
     */
    public function show($id)
    {
        $short = Short::with('user:id,nickname,avatar')->findOrFail($id);

        $short->liked = false;
        if (Auth::check()) {
            $short->liked = ShortLike::where('user_id', Auth::id())
                ->where('short_id', $id)
                ->exists();
        }

        return response()->json(['success' => true, 'data' => $short]);
    }

    /**
     * POST /api/shorts
     * Create short (youtube URL parsing to extract youtube_id, thumbnail)
     */
    public function store(Request $request)
    {
        $request->validate([
            'url'         => 'required|url',
            'title'       => 'nullable|string|max:100',
            'description' => 'nullable|string|max:300',
            'tags'        => 'nullable|array|max:5',
        ]);

        $url = $request->url;

        // YouTube Shorts only validation
        if (preg_match('/youtube\.com|youtu\.be/i', $url)) {
            if (!preg_match('/youtube\.com\/shorts\//i', $url)) {
                return response()->json([
                    'success' => false,
                    'message' => '세로형 YouTube Shorts URL만 등록 가능합니다. (youtube.com/shorts/...)',
                ], 422);
            }
        }

        $parsed = $this->parseEmbed($url);

        $short = Short::create([
            'user_id'     => Auth::id(),
            'url'         => $url,
            'embed_url'   => $parsed['embed_url'],
            'platform'    => $parsed['platform'],
            'thumbnail'   => $parsed['thumbnail'],
            'title'       => $request->title,
            'description' => $request->description,
            'tags'        => $request->tags ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => '숏츠가 등록되었습니다.',
            'data'    => $short->load('user:id,nickname,avatar'),
        ], 201);
    }

    /**
     * POST /api/shorts/{id}/like
     * Toggle like/unlike
     */
    public function toggleLike($id)
    {
        $short = Short::findOrFail($id);
        $userId = Auth::id();

        $existing = ShortLike::where('user_id', $userId)->where('short_id', $id)->first();

        if ($existing) {
            $existing->delete();
            $short->decrement('like_count');
            return response()->json([
                'success' => true,
                'data'    => ['liked' => false, 'like_count' => $short->fresh()->like_count],
            ]);
        }

        ShortLike::create(['user_id' => $userId, 'short_id' => $id]);
        $short->increment('like_count');

        return response()->json([
            'success' => true,
            'data'    => ['liked' => true, 'like_count' => $short->fresh()->like_count],
        ]);
    }

    /**
     * POST /api/shorts/{id}/view
     * Increment view count
     */
    public function view($id)
    {
        Short::where('id', $id)->increment('view_count');
        return response()->json(['success' => true]);
    }

    /**
     * GET /api/shorts/my
     */
    public function myShorts()
    {
        $shorts = Short::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $shorts]);
    }

    /**
     * DELETE /api/shorts/{id}
     */
    public function destroy($id)
    {
        $short = Short::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $short->delete();

        return response()->json(['success' => true, 'message' => '삭제되었습니다.']);
    }

    /**
     * GET /api/shorts/feed
     * Personalized feed
     */
    public function feed(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $limit = 10;
        $user = Auth::user();

        $query = Short::with('user:id,nickname,avatar')
            ->where('is_active', true);

        if ($user) {
            $interests = DB::table('user_interests')
                ->where('user_id', $user->id)
                ->value('tags');
            $tags = $interests ? json_decode($interests, true) : [];

            if (!empty($tags)) {
                $query->orderByRaw(
                    'CASE WHEN ' . collect($tags)->map(fn($t) => "JSON_CONTAINS(tags, ?)")->implode(' OR ') . ' THEN 0 ELSE 1 END',
                    collect($tags)->map(fn($t) => '"' . $t . '"')->values()->toArray()
                );
            }
        }

        $shorts = $query->orderByDesc('like_count')
            ->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        // Append liked status
        if ($user) {
            $shortIds = $shorts->pluck('id')->toArray();
            $likedIds = ShortLike::where('user_id', $user->id)
                ->whereIn('short_id', $shortIds)
                ->pluck('short_id')
                ->toArray();

            $shorts->transform(function ($s) use ($likedIds) {
                $s->liked = in_array($s->id, $likedIds);
                return $s;
            });
        }

        return response()->json([
            'success' => true,
            'data'    => $shorts,
            'page'    => $page,
        ]);
    }

    /**
     * Parse URL into embed data
     */
    private function parseEmbed(string $url): array
    {
        // YouTube Shorts / Watch / Short URL
        if (preg_match('/youtube\.com\/shorts\/([a-zA-Z0-9_-]+)/', $url, $m) ||
            preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $m) ||
            preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $url, $m)) {
            $vid = $m[1];
            return [
                'platform'  => 'youtube',
                'embed_url' => "https://www.youtube.com/embed/{$vid}?autoplay=1&mute=1&controls=1&loop=1&playlist={$vid}&rel=0",
                'thumbnail' => "https://img.youtube.com/vi/{$vid}/hqdefault.jpg",
            ];
        }

        // TikTok
        if (preg_match('/tiktok\.com\/@[^\/]+\/video\/(\d+)/', $url, $m)) {
            return [
                'platform'  => 'tiktok',
                'embed_url' => "https://www.tiktok.com/embed/v2/{$m[1]}",
                'thumbnail' => null,
            ];
        }

        // Instagram Reels
        if (preg_match('/instagram\.com\/(?:p|reel)\/([a-zA-Z0-9_-]+)/', $url, $m)) {
            return [
                'platform'  => 'instagram',
                'embed_url' => "https://www.instagram.com/p/{$m[1]}/embed/",
                'thumbnail' => null,
            ];
        }

        return ['platform' => 'other', 'embed_url' => $url, 'thumbnail' => null];
    }
}
