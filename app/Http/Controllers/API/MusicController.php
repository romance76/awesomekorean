<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\{MusicCategory, MusicTrack, UserPlaylist, UserPlaylistTrack};
use Illuminate\Http\Request;

class MusicController extends Controller
{
    public function categories()
    {
        return response()->json(['success' => true, 'data' => MusicCategory::orderBy('sort_order')->get()]);
    }

    public function tracks(Request $request, $categoryId)
    {
        $query = MusicTrack::where('category_id', $categoryId)->inRandomOrder();
        $perPage = $request->per_page ?? 20;
        return response()->json(['success' => true, 'data' => $query->paginate($perPage)]);
    }

    // 내 플레이리스트 목록
    public function playlists()
    {
        $playlists = UserPlaylist::where('user_id', auth()->id())
            ->withCount('tracks')
            ->orderByDesc('created_at')
            ->get();
        return response()->json(['success' => true, 'data' => $playlists]);
    }

    // 플레이리스트 생성
    public function createPlaylist(Request $request)
    {
        $request->validate(['name' => 'required|max:100']);
        $playlist = UserPlaylist::create(['user_id' => auth()->id(), 'name' => $request->name]);
        return response()->json(['success' => true, 'data' => $playlist], 201);
    }

    // 플레이리스트 상세 (트랙 포함)
    public function getPlaylist($id)
    {
        $playlist = UserPlaylist::where('user_id', auth()->id())
            ->with('tracks.track')
            ->findOrFail($id);
        return response()->json(['success' => true, 'data' => $playlist]);
    }

    // 플레이리스트에 트랙 추가
    public function addTrack(Request $request, $id)
    {
        $playlist = UserPlaylist::where('user_id', auth()->id())->findOrFail($id);
        $request->validate(['track_id' => 'required|exists:music_tracks,id']);

        if (UserPlaylistTrack::where('playlist_id', $id)->where('track_id', $request->track_id)->exists()) {
            return response()->json(['success' => false, 'message' => '이미 추가된 트랙입니다'], 400);
        }

        $maxOrder = UserPlaylistTrack::where('playlist_id', $id)->max('sort_order') ?? 0;
        UserPlaylistTrack::create([
            'playlist_id' => $id,
            'track_id' => $request->track_id,
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json(['success' => true, 'message' => '트랙이 추가되었습니다']);
    }

    // 플레이리스트에서 트랙 제거
    public function removeTrack($id, $trackId)
    {
        UserPlaylistTrack::where('playlist_id', $id)->where('track_id', $trackId)->delete();
        return response()->json(['success' => true, 'message' => '트랙이 제거되었습니다']);
    }

    // 플레이리스트 삭제
    public function deletePlaylist($id)
    {
        UserPlaylist::where('user_id', auth()->id())->findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => '플레이리스트가 삭제되었습니다']);
    }

    // 트랙 검색 (전체)
    public function searchTracks(Request $request)
    {
        $q = $request->q;
        if (!$q) return response()->json(['success' => true, 'data' => []]);
        $tracks = MusicTrack::where('title', 'like', "%{$q}%")
            ->orWhere('artist', 'like', "%{$q}%")
            ->limit(50)->get();
        return response()->json(['success' => true, 'data' => $tracks]);
    }

    // 즐겨찾기 토글
    public function toggleFavorite(Request $request)
    {
        $request->validate(['track_id' => 'required|integer']);
        $userId = auth()->id();
        $trackId = $request->track_id;
        $existing = \DB::table('music_favorites')->where('user_id', $userId)->where('track_id', $trackId)->first();
        if ($existing) {
            \DB::table('music_favorites')->where('id', $existing->id)->delete();
            return response()->json(['success' => true, 'favorited' => false]);
        }
        \DB::table('music_favorites')->insert(['user_id' => $userId, 'track_id' => $trackId, 'created_at' => now()]);
        return response()->json(['success' => true, 'favorited' => true]);
    }

    // 즐겨찾기 목록
    public function favorites()
    {
        $ids = \DB::table('music_favorites')->where('user_id', auth()->id())->pluck('track_id');
        $tracks = MusicTrack::whereIn('id', $ids)->get();
        return response()->json(['success' => true, 'data' => $tracks]);
    }

    // 관리자: 카테고리 추가
    public function storeCategory(Request $request)
    {
        $request->validate(['name' => 'required|max:50']);
        $slug = $request->slug ?: \Illuminate\Support\Str::slug($request->name);
        $cat = MusicCategory::create([
            'name' => $request->name,
            'slug' => $slug,
            'sort_order' => MusicCategory::max('sort_order') + 1,
            'korean_queries' => $request->korean_queries,
            'pop_queries' => $request->pop_queries,
        ]);
        return response()->json(['success' => true, 'data' => $cat], 201);
    }

    // 관리자: 트랙 추가
    public function storeTrack(Request $request)
    {
        $request->validate(['title' => 'required', 'category_id' => 'required|exists:music_categories,id']);

        // YouTube URL에서 ID 추출
        $ytId = null;
        if ($request->youtube_url) {
            preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/))([a-zA-Z0-9_-]+)/', $request->youtube_url, $m);
            $ytId = $m[1] ?? null;
        }

        $track = MusicTrack::create([
            'category_id' => $request->category_id,
            'title' => $request->title,
            'artist' => $request->artist,
            'youtube_url' => $request->youtube_url,
            'youtube_id' => $ytId,
            'duration' => $request->duration ?? 0,
        ]);
        return response()->json(['success' => true, 'data' => $track], 201);
    }

    public function destroyTrack($id)
    {
        MusicTrack::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
}
