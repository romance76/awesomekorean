<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * YouTube 채널 URL/핸들 → channel ID 변환 + 채널/플레이리스트 영상 목록 조회.
 * MusicController::bulkImport()(관리자 수동 "채널 가져오기")와
 * FetchMusicTracks(카테고리별 자동수집 — 채널이 지정된 카테고리)가
 * 공통으로 쓰는 로직이라 여기로 분리함.
 */
class YoutubeChannelResolver
{
    /**
     * 여러 형식의 YouTube 채널 입력을 channel ID(UCxxx)로 변환.
     * 지원: /channel/UCxxx, /@핸들(한글 포함), UCxxx 직접, /c/xxx, /user/xxx, 검색어
     */
    public function resolveChannelId(string $apiKey, string $input): ?string
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
        if (preg_match('~/channel/(UC[\w-]{20,})~', $decoded, $m)) {
            return $m[1];
        }

        // 3. /@핸들 패턴 (한글/유니코드 지원)
        if (preg_match('~/@([^/?\s]+)~u', $decoded, $m)) {
            $handle = explode('#', $m[1])[0]; // 혹시 모를 # 이후 제거
            return $this->handleToChannelId($apiKey, $handle);
        }

        // 4. 순수 @핸들 입력
        if (preg_match('/^@(.+)$/u', $decoded, $m)) {
            return $this->handleToChannelId($apiKey, $m[1]);
        }

        // 5. /c/xxx 또는 /user/xxx (legacy) → 검색으로 추정
        if (preg_match('~/(?:c|user)/([^/?\s]+)~u', $decoded, $m)) {
            $name = explode('#', $m[1])[0];
            return $this->searchChannelByName($apiKey, $name);
        }

        // 6. 그냥 채널 이름/검색어 입력
        return $this->searchChannelByName($apiKey, $decoded);
    }

    /**
     * @핸들(한글 포함) → channel ID
     * 1) channels.list?forHandle 시도 (YouTube API v3 공식)
     * 2) 실패 시 search.list 폴백
     */
    public function handleToChannelId(string $apiKey, string $handle): ?string
    {
        $handle = ltrim($handle, '@');
        if ($handle === '') return null;

        try {
            $res = Http::get('https://www.googleapis.com/youtube/v3/channels', [
                'key' => $apiKey,
                'forHandle' => '@' . $handle,
                'part' => 'id',
            ]);
            if ($res->ok()) {
                $id = $res->json('items.0.id');
                if ($id) return $id;
            }
        } catch (\Exception $e) {}

        return $this->searchChannelByName($apiKey, $handle);
    }

    public function searchChannelByName(string $apiKey, string $name): ?string
    {
        try {
            $res = Http::get('https://www.googleapis.com/youtube/v3/search', [
                'key' => $apiKey,
                'q' => $name,
                'type' => 'channel',
                'part' => 'snippet',
                'maxResults' => 1,
            ]);
            if ($res->ok()) {
                $cid = $res->json('items.0.snippet.channelId') ?: $res->json('items.0.id.channelId');
                if ($cid) return $cid;
            }
        } catch (\Exception $e) {}
        return null;
    }

    public function fetchPlaylistVideos(string $apiKey, string $playlistId, int $maxItems = 200): array
    {
        $ids = [];
        $pageToken = null;
        do {
            $res = Http::get('https://www.googleapis.com/youtube/v3/playlistItems', [
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

    public function fetchChannelVideos(string $apiKey, string $channelId, int $maxItems = 200): array
    {
        // 채널의 uploads 플레이리스트 ID 가져오기
        $res = Http::get('https://www.googleapis.com/youtube/v3/channels', [
            'key' => $apiKey, 'id' => $channelId, 'part' => 'contentDetails',
        ]);
        $uploadsId = $res->json('items.0.contentDetails.relatedPlaylists.uploads');
        if (!$uploadsId) return [];
        return $this->fetchPlaylistVideos($apiKey, $uploadsId, $maxItems);
    }
}
