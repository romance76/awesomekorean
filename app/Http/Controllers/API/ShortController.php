<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Short;
use Illuminate\Http\Request;

class ShortController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();

        if ($userId) {
            // 로그인: 안 본 숏츠 먼저 (랜덤) + 본 숏츠 나중에 (랜덤)
            $viewedIds = \DB::table('short_views')->where('user_id', $userId)->pluck('short_id')->toArray();

            $unseen = Short::where('is_active', true)
                ->whereNotIn('id', $viewedIds)
                ->inRandomOrder()
                ->limit(50)
                ->get();

            $seen = Short::where('is_active', true)
                ->whereIn('id', $viewedIds)
                ->inRandomOrder()
                ->limit(20)
                ->get();

            $shorts = $unseen->concat($seen);
        } else {
            // 비로그인: 전체 랜덤
            $shorts = Short::where('is_active', true)->inRandomOrder()->limit(50)->get();
        }

        return response()->json(['success' => true, 'data' => ['data' => $shorts]]);
    }

    // 숏츠 본 기록 저장
    public function markViewed(Request $request, $id)
    {
        $userId = auth()->id();
        if (!$userId) return response()->json(['success' => true]);

        \DB::table('short_views')->updateOrInsert(
            ['user_id' => $userId, 'short_id' => $id],
            ['viewed_at' => now()]
        );
        return response()->json(['success' => true]);
    }

    public function store(Request $request)
    {
        $request->validate(['title' => 'required|max:200', 'video_url' => 'required|url']);
        preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|shorts\/))([a-zA-Z0-9_-]+)/', $request->video_url, $m);
        $ytId = $m[1] ?? null;

        $short = Short::create([
            'user_id' => auth()->id(), 'title' => $request->title, 'video_url' => $request->video_url,
            'youtube_id' => $ytId, 'thumbnail_url' => $ytId ? "https://img.youtube.com/vi/{$ytId}/hqdefault.jpg" : null,
        ]);
        return response()->json(['success' => true, 'data' => $short], 201);
    }

    public function toggleLike($id)
    {
        $short = Short::findOrFail($id);
        $like = \App\Models\ShortLike::where('short_id', $id)->where('user_id', auth()->id())->first();
        if ($like) { $like->delete(); $short->decrement('like_count'); return response()->json(['success' => true, 'liked' => false]); }
        \App\Models\ShortLike::create(['short_id' => $id, 'user_id' => auth()->id()]);
        $short->increment('like_count');
        return response()->json(['success' => true, 'liked' => true]);
    }
}
