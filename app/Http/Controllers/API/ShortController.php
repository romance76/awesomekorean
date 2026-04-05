<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Short;
use Illuminate\Http\Request;

class ShortController extends Controller
{
    public function index(Request $request)
    {
        $query = Short::with('user:id,name,nickname,avatar')->where('is_active', true);
        if ($request->sort === 'random') $query->inRandomOrder(); else $query->orderByDesc('created_at');
        return response()->json(['success' => true, 'data' => $query->paginate(20)]);
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
        $like = \App\Models\ShortLike::where('short_id',$id)->where('user_id',auth()->id())->first();
        if ($like) { $like->delete(); $short->decrement('like_count'); return response()->json(['success'=>true,'liked'=>false]); }
        \App\Models\ShortLike::create(['short_id'=>$id,'user_id'=>auth()->id()]);
        $short->increment('like_count');
        return response()->json(['success'=>true,'liked'=>true]);
    }
}
