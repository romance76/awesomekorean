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
        // 유저에게 노출되는 트랙은 반드시 5분 이하(300s)여야 하며,
        // duration=0(라이브/믹스)은 표시 안 함. 유저 업로드는 예외.
        $query = MusicTrack::where('category_id', $categoryId)
            ->where(function ($q) {
                $q->where('is_user_submitted', true)
                  ->orWhere(function ($q2) {
                      $q2->where('duration', '>', 0)->where('duration', '<=', 300);
                  });
            })
            ->inRandomOrder();
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
        $slug = $request->slug ?: \Illuminate\Support\Str::slug($request->name) ?: 'cat-'.time();
        $cat = MusicCategory::create([
            'name' => $request->name,
            'slug' => $slug,
            'sort_order' => (MusicCategory::max('sort_order') ?? 0) + 1,
            'korean_queries' => $request->korean_queries,
            'pop_queries' => $request->pop_queries,
        ]);
        return response()->json(['success' => true, 'data' => $cat], 201);
    }

    // 관리자: 카테고리 이름 변경
    public function updateCategory(Request $request, $id)
    {
        $cat = MusicCategory::findOrFail($id);
        $request->validate(['name' => 'required|max:50']);
        $cat->update([
            'name' => $request->name,
            'slug' => $request->slug ?: $cat->slug,
        ]);
        return response()->json(['success' => true, 'data' => $cat]);
    }

    // 관리자: 카테고리 삭제 (소속 트랙은 '미분류' 이동 or 함께 삭제 선택)
    public function destroyCategory(Request $request, $id)
    {
        $cat = MusicCategory::findOrFail($id);
        $trackCount = MusicTrack::where('category_id', $id)->count();

        if ($trackCount > 0 && !$request->boolean('delete_tracks')) {
            return response()->json([
                'success' => false,
                'message' => "이 카테고리에 {$trackCount}개 트랙이 있습니다. delete_tracks=true 를 전달해 함께 삭제하세요.",
                'track_count' => $trackCount,
            ], 409);
        }

        if ($trackCount > 0) {
            MusicTrack::where('category_id', $id)->delete();
        }
        $cat->delete();
        return response()->json(['success' => true, 'deleted_tracks' => $trackCount]);
    }

    // 관리자: YouTube 일괄 가져오기 (playlist / channel / urls)
    public function bulkImport(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:music_categories,id',
            'mode' => 'required|in:playlist,channel,urls',
            'url' => 'required|string',
        ]);

        $apiKey = $this->getYoutubeApiKey();
        if (!$apiKey) return response()->json(['success' => false, 'message' => 'YouTube API 키가 설정되지 않았습니다'], 500);

        $catId = (int) $request->category_id;
        $mode = $request->mode;
        $input = trim($request->url);

        $videoIds = [];
        $errors = [];

        try {
            if ($mode === 'playlist') {
                // playlist ID 추출
                if (preg_match('/[?&]list=([a-zA-Z0-9_-]+)/', $input, $m)) {
                    $plId = $m[1];
                } elseif (preg_match('/^[a-zA-Z0-9_-]{10,}$/', $input)) {
                    $plId = $input;
                } else {
                    return response()->json(['success' => false, 'message' => '플레이리스트 URL이 올바르지 않습니다'], 422);
                }
                $videoIds = $this->fetchPlaylistVideos($apiKey, $plId, 200);
            } elseif ($mode === 'channel') {
                $channelId = $this->resolveChannelId($apiKey, $input);
                if (!$channelId) return response()->json(['success' => false, 'message' => '채널을 찾을 수 없습니다. URL을 확인해주세요.'], 422);
                $videoIds = $this->fetchChannelVideos($apiKey, $channelId, 200);
            } else { // urls
                $lines = preg_split('/[\s,\r\n]+/', $input);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!$line) continue;
                    if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|shorts\/|embed\/))([a-zA-Z0-9_-]{11})/', $line, $m)) {
                        $videoIds[] = $m[1];
                    } elseif (preg_match('/^[a-zA-Z0-9_-]{11}$/', $line)) {
                        $videoIds[] = $line;
                    }
                }
                $videoIds = array_unique($videoIds);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => '가져오기 실패: '.$e->getMessage()], 500);
        }

        if (empty($videoIds)) {
            return response()->json(['success' => false, 'message' => '가져올 영상이 없습니다'], 422);
        }

        // 배치 처리 (YouTube videos.list는 최대 50개씩)
        $added = 0; $skipped = 0; $skipReasons = [];
        foreach (array_chunk($videoIds, 50) as $chunk) {
            $res = \Illuminate\Support\Facades\Http::get('https://www.googleapis.com/youtube/v3/videos', [
                'key' => $apiKey, 'id' => implode(',', $chunk), 'part' => 'snippet,contentDetails',
            ]);
            if (!$res->ok()) continue;

            foreach ($res->json('items', []) as $v) {
                $vid = $v['id'] ?? null;
                if (!$vid) { $skipped++; continue; }
                if (MusicTrack::where('youtube_id', $vid)->exists()) { $skipped++; $skipReasons[] = '중복'; continue; }

                $title = $v['snippet']['title'] ?? '';
                $channel = $v['snippet']['channelTitle'] ?? '';
                $iso = $v['contentDetails']['duration'] ?? 'PT0S';
                preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $iso, $dm);
                $seconds = (intval($dm[1] ?? 0) * 3600) + (intval($dm[2] ?? 0) * 60) + intval($dm[3] ?? 0);

                // duration 필수 (라이브/미확인 차단)
                if ($seconds <= 0) { $skipped++; $skipReasons[] = '라이브/길이불명'; continue; }
                // 5분 초과 차단
                if ($seconds > 300) { $skipped++; $skipReasons[] = '5분초과'; continue; }
                // 너무 짧음 (10초 미만)
                if ($seconds < 10) { $skipped++; $skipReasons[] = '10초미만'; continue; }
                if (mb_strlen($title) < 2) { $skipped++; continue; }

                // 언어 필터
                $text = $title . ' ' . $channel;
                if (preg_match('/[\x{3040}-\x{309F}]|[\x{30A0}-\x{30FF}]/u', $text)) { $skipped++; $skipReasons[] = '일본어'; continue; }
                if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $text) && !preg_match('/[\x{AC00}-\x{D7AF}]/u', $text)) { $skipped++; $skipReasons[] = '중국어'; continue; }
                if (preg_match('/[\x{0900}-\x{097F}]|[\x{0600}-\x{06FF}]|[\x{0E00}-\x{0E7F}]|[\x{0980}-\x{09FF}]/u', $text)) { $skipped++; $skipReasons[] = '힌디/아랍/태국'; continue; }
                if (preg_match('/[ăâđêôơưừứửữựắằẳẵặẻẽẹểễệốồổỗộớờởỡợýỷỹỵ]/u', $text)) { $skipped++; $skipReasons[] = '베트남어'; continue; }
                if (preg_match('/[áéíóúñ¿¡]/u', $text)) { $skipped++; $skipReasons[] = '스페인어'; continue; }

                MusicTrack::create([
                    'category_id' => $catId,
                    'title' => mb_substr($title, 0, 200),
                    'artist' => mb_substr($channel, 0, 100),
                    'youtube_id' => $vid,
                    'youtube_url' => "https://www.youtube.com/watch?v={$vid}",
                    'duration' => $seconds,
                    'sort_order' => 0,
                    'is_user_submitted' => false,
                ]);
                $added++;
            }
        }

        return response()->json([
            'success' => true,
            'added' => $added,
            'skipped' => $skipped,
            'total_found' => count($videoIds),
            'skip_reasons' => array_count_values($skipReasons),
            'message' => "추가 {$added}곡 / 제외 {$skipped}곡 (총 ".count($videoIds)."개 영상)",
        ]);
    }

    // ─── helpers ───
    private function getYoutubeApiKey()
    {
        $apiKey = config('services.youtube.api_key');
        if (!$apiKey && file_exists(base_path('.env'))) {
            if (preg_match('/YOUTUBE_API_KEY=(.+)/', file_get_contents(base_path('.env')), $m)) {
                $apiKey = trim($m[1]);
            }
        }
        return $apiKey;
    }

    private function fetchPlaylistVideos($apiKey, $playlistId, $maxItems = 200)
    {
        $ids = [];
        $pageToken = null;
        do {
            $res = \Illuminate\Support\Facades\Http::get('https://www.googleapis.com/youtube/v3/playlistItems', [
                'key' => $apiKey, 'playlistId' => $playlistId, 'part' => 'snippet',
                'maxResults' => 50, 'pageToken' => $pageToken,
            ]);
            if (!$res->ok()) break;
            foreach ($res->json('items', []) as $item) {
                $vid = $item['snippet']['resourceId']['videoId'] ?? null;
                if ($vid) $ids[] = $vid;
                if (count($ids) >= $maxItems) break 2;
            }
            $pageToken = $res->json('nextPageToken');
        } while ($pageToken);
        return $ids;
    }

    private function fetchChannelVideos($apiKey, $channelId, $maxItems = 200)
    {
        // 채널의 uploads 플레이리스트 ID 가져오기
        $res = \Illuminate\Support\Facades\Http::get('https://www.googleapis.com/youtube/v3/channels', [
            'key' => $apiKey, 'id' => $channelId, 'part' => 'contentDetails',
        ]);
        $uploadsId = $res->json('items.0.contentDetails.relatedPlaylists.uploads');
        if (!$uploadsId) return [];
        return $this->fetchPlaylistVideos($apiKey, $uploadsId, $maxItems);
    }

    /**
     * 여러 형식의 YouTube 채널 입력을 channel ID(UCxxx)로 변환.
     * 지원: /channel/UCxxx, /@핸들(한글 포함), UCxxx 직접, /c/xxx, /user/xxx, 검색어
     */
    private function resolveChannelId($apiKey, $input)
    {
        $input = trim($input);
        if ($input === '') return null;

        // 0. URL 디코딩 (한글 핸들 지원)
        $decoded = urldecode($input);

        // 1. 직접 UC로 시작하는 channel ID
        if (preg_match('/^UC[\w-]{20,}$/', $decoded)) {
            return $decoded;
        }

        // 2. /channel/UCxxx 패턴
        if (preg_match('#/channel/(UC[\w-]{20,})#', $decoded, $m)) {
            return $m[1];
        }

        // 3. /@핸들 패턴 (한글/유니코드 지원)
        if (preg_match('#/@([^/?#\s]+)#u', $decoded, $m)) {
            $handle = $m[1];
            return $this->handleToChannelId($apiKey, $handle);
        }

        // 4. 순수 @핸들 입력
        if (preg_match('/^@(.+)$/u', $decoded, $m)) {
            return $this->handleToChannelId($apiKey, $m[1]);
        }

        // 5. /c/xxx 또는 /user/xxx (legacy) → 검색으로 추정
        if (preg_match('#/(?:c|user)/([^/?#\s]+)#u', $decoded, $m)) {
            return $this->searchChannelByName($apiKey, $m[1]);
        }

        // 6. 그냥 채널 이름/검색어 입력
        return $this->searchChannelByName($apiKey, $decoded);
    }

    /**
     * @핸들(한글 포함) → channel ID
     * 1) channels.list?forHandle 시도 (YouTube API v3 공식)
     * 2) 실패 시 search.list 폴백
     */
    private function handleToChannelId($apiKey, $handle)
    {
        $handle = ltrim($handle, '@');
        if ($handle === '') return null;

        // 1) forHandle 직접 조회 (YouTube 공식, 한글 핸들 지원)
        try {
            $res = \Illuminate\Support\Facades\Http::get('https://www.googleapis.com/youtube/v3/channels', [
                'key' => $apiKey,
                'forHandle' => '@' . $handle,
                'part' => 'id',
            ]);
            if ($res->ok()) {
                $id = $res->json('items.0.id');
                if ($id) return $id;
            }
        } catch (\Exception $e) {}

        // 2) search 폴백 (핸들 이름으로 검색)
        return $this->searchChannelByName($apiKey, $handle);
    }

    private function searchChannelByName($apiKey, $name)
    {
        try {
            $res = \Illuminate\Support\Facades\Http::get('https://www.googleapis.com/youtube/v3/search', [
                'key' => $apiKey,
                'q' => $name,
                'type' => 'channel',
                'part' => 'snippet',
                'maxResults' => 1,
            ]);
            if ($res->ok()) {
                // 결과에서 channelId 또는 id.channelId 추출
                $cid = $res->json('items.0.snippet.channelId') ?: $res->json('items.0.id.channelId');
                if ($cid) return $cid;
            }
        } catch (\Exception $e) {}
        return null;
    }

    // 관리자: 트랙 추가 (YouTube API로 duration 조회 후 5분 이하만 허용)
    public function storeTrack(Request $request)
    {
        $request->validate(['title' => 'required', 'category_id' => 'required|exists:music_categories,id']);

        // YouTube URL에서 ID 추출
        $ytId = null;
        if ($request->youtube_url) {
            preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/))([a-zA-Z0-9_-]+)/', $request->youtube_url, $m);
            $ytId = $m[1] ?? null;
        }

        // YouTube API로 duration 조회
        $duration = (int) ($request->duration ?? 0);
        if ($ytId && $duration === 0) {
            $apiKey = config('services.youtube.api_key');
            if (!$apiKey && file_exists(base_path('.env')) && preg_match('/YOUTUBE_API_KEY=(.+)/', file_get_contents(base_path('.env')), $em)) {
                $apiKey = trim($em[1]);
            }
            if ($apiKey) {
                try {
                    $resp = \Illuminate\Support\Facades\Http::timeout(5)->get('https://www.googleapis.com/youtube/v3/videos', [
                        'key' => $apiKey, 'id' => $ytId, 'part' => 'contentDetails',
                    ]);
                    if ($resp->ok()) {
                        $iso = $resp->json('items.0.contentDetails.duration', 'PT0S');
                        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $iso, $dm);
                        $duration = (intval($dm[1] ?? 0) * 3600) + (intval($dm[2] ?? 0) * 60) + intval($dm[3] ?? 0);
                    }
                } catch (\Exception $e) {}
            }
        }

        // 5분 초과 차단 (관리자도 불가)
        if ($duration > 300) {
            return response()->json(['success' => false, 'message' => '5분 초과 영상은 추가할 수 없습니다 ('.floor($duration/60).'분 '.($duration%60).'초)'], 422);
        }
        if ($duration <= 0) {
            return response()->json(['success' => false, 'message' => 'YouTube에서 영상 길이를 확인할 수 없습니다. 라이브/믹스는 허용되지 않습니다.'], 422);
        }

        $track = MusicTrack::create([
            'category_id' => $request->category_id,
            'title' => $request->title,
            'artist' => $request->artist,
            'youtube_url' => $request->youtube_url,
            'youtube_id' => $ytId,
            'duration' => $duration,
        ]);
        return response()->json(['success' => true, 'data' => $track], 201);
    }

    public function destroyTrack($id)
    {
        MusicTrack::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
}
