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

    // YouTube 링크로 플레이리스트에 추가
    public function importYoutube(Request $request)
    {
        $request->validate(['url' => 'required|string', 'playlist_id' => 'required|integer']);
        $url = $request->url;
        $plId = $request->playlist_id;

        // 플레이리스트 소유 확인
        $pl = UserPlaylist::where('id', $plId)->where('user_id', auth()->id())->first();
        if (!$pl) return response()->json(['success' => false, 'message' => '플레이리스트를 찾을 수 없습니다'], 404);

        $apiKey = config('services.youtube.api_key');
        if (!$apiKey) {
            $envPath = base_path('.env');
            if (file_exists($envPath)) {
                $envContent = file_get_contents($envPath);
                if (preg_match('/YOUTUBE_API_KEY=(.+)/', $envContent, $m)) $apiKey = trim($m[1]);
            }
        }
        if (!$apiKey) return response()->json(['success' => false, 'message' => 'YouTube API 키가 없습니다'], 500);

        $added = 0;

        // 플레이리스트 URL 감지
        if (preg_match('/[?&]list=([a-zA-Z0-9_-]+)/', $url, $m)) {
            $listId = $m[1];
            $nextPage = '';
            $maxItems = 50;
            $fetched = 0;

            do {
                $params = ['key' => $apiKey, 'playlistId' => $listId, 'part' => 'snippet', 'maxResults' => 50];
                if ($nextPage) $params['pageToken'] = $nextPage;

                $response = \Illuminate\Support\Facades\Http::get('https://www.googleapis.com/youtube/v3/playlistItems', $params);
                if (!$response->ok()) break;

                $items = $response->json('items', []);
                foreach ($items as $item) {
                    if ($fetched >= $maxItems) break;
                    $videoId = $item['snippet']['resourceId']['videoId'] ?? null;
                    if (!$videoId) continue;
                    $title = $item['snippet']['title'] ?? '';
                    $channel = $item['snippet']['channelTitle'] ?? '';
                    if ($title === 'Private video' || $title === 'Deleted video') continue;

                    // DB에 트랙 저장 (없으면 생성) - 유저 업로드로 표시
                    $track = MusicTrack::firstOrCreate(
                        ['youtube_id' => $videoId],
                        ['title' => mb_substr($title, 0, 200), 'artist' => mb_substr($channel, 0, 100), 'youtube_id' => $videoId, 'youtube_url' => "https://www.youtube.com/watch?v={$videoId}", 'category_id' => 1, 'duration' => 0, 'sort_order' => 0, 'is_user_submitted' => true]
                    );

                    // 플레이리스트에 추가 (중복 체크)
                    if (!UserPlaylistTrack::where('playlist_id', $plId)->where('track_id', $track->id)->exists()) {
                        UserPlaylistTrack::create(['playlist_id' => $plId, 'track_id' => $track->id, 'sort_order' => 0]);
                        $added++;
                    }
                    $fetched++;
                }

                $nextPage = $response->json('nextPageToken');
            } while ($nextPage && $fetched < $maxItems);

            return response()->json(['success' => true, 'added' => $added, 'message' => "플레이리스트에서 {$added}곡 추가 완료!"]);
        }

        // 단일 곡 URL
        $videoId = null;
        if (preg_match('/[?&]v=([a-zA-Z0-9_-]{11})/', $url, $m)) $videoId = $m[1];
        elseif (preg_match('/youtu\.be\/([a-zA-Z0-9_-]{11})/', $url, $m)) $videoId = $m[1];

        if (!$videoId) return response()->json(['success' => false, 'message' => 'YouTube URL을 인식할 수 없습니다'], 422);

        // 영상 정보 가져오기
        $response = \Illuminate\Support\Facades\Http::get('https://www.googleapis.com/youtube/v3/videos', [
            'key' => $apiKey, 'id' => $videoId, 'part' => 'snippet,contentDetails',
        ]);

        if (!$response->ok()) return response()->json(['success' => false, 'message' => 'YouTube API 오류'], 500);

        $items = $response->json('items', []);
        if (empty($items)) return response()->json(['success' => false, 'message' => '영상을 찾을 수 없습니다'], 404);

        $item = $items[0];
        $title = $item['snippet']['title'] ?? '';
        $channel = $item['snippet']['channelTitle'] ?? '';
        $dur = $item['contentDetails']['duration'] ?? 'PT0S';
        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $dur, $dm);
        $seconds = (intval($dm[1] ?? 0) * 3600) + (intval($dm[2] ?? 0) * 60) + intval($dm[3] ?? 0);

        $track = MusicTrack::firstOrCreate(
            ['youtube_id' => $videoId],
            ['title' => mb_substr($title, 0, 200), 'artist' => mb_substr($channel, 0, 100), 'youtube_id' => $videoId, 'youtube_url' => "https://www.youtube.com/watch?v={$videoId}", 'category_id' => 1, 'duration' => $seconds, 'sort_order' => 0, 'is_user_submitted' => true]
        );

        if (!UserPlaylistTrack::where('playlist_id', $plId)->where('track_id', $track->id)->exists()) {
            UserPlaylistTrack::create(['playlist_id' => $plId, 'track_id' => $track->id, 'sort_order' => 0]);
            return response()->json(['success' => true, 'added' => 1, 'message' => "'{$title}' 추가 완료!"]);
        }

        return response()->json(['success' => true, 'added' => 0, 'message' => '이미 추가된 곡입니다']);
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
