<?php

namespace App\Support;

use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use Illuminate\Support\Facades\Http;

/**
 * 한인 유튜버 소개: 유튜브 공식 API(Data API v3)로 채널 정보와 최근 영상을 읽어 온다.
 * 키는 .env 의 YOUTUBE_API_KEY (config services.youtube.api_key). 호출 비용: 채널 1곳 갱신 = 약 3유닛(하루 한도 1만).
 * 영상 재생은 유튜브 공식 임베드를 쓰므로 영상 파일을 내려받거나 다시 올리지 않는다.
 */
class YoutubeSync
{
    private const API = 'https://www.googleapis.com/youtube/v3/';
    /** 이 길이(초) 이하는 '쇼츠' 탭으로 분류 */
    public const SHORT_MAX_SECONDS = 60;

    private static function key(): ?string
    {
        $k = config('services.youtube.api_key');
        return is_string($k) && $k !== '' ? $k : null;
    }

    /** 신청 칸에 적은 채널 주소·@이름·UC번호에서 검색 조건을 뽑는다. ['id'=>'UC..'] 또는 ['forHandle'=>'@x'] 또는 null */
    public static function parseInput(string $input): ?array
    {
        $s = trim($input);
        if ($s === '' || mb_strlen($s) > 200) return null;
        if (preg_match('#youtube\.com/channel/(UC[A-Za-z0-9_\-]{20,30})#i', $s, $m)) return ['id' => $m[1]];
        if (preg_match('#^(UC[A-Za-z0-9_\-]{20,30})$#', $s, $m)) return ['id' => $m[1]];
        if (preg_match('#youtube\.com/@([^/?\#\s]+)#iu', rawurldecode($s), $m)) return ['forHandle' => '@' . $m[1]];
        if (preg_match('#^@([^\s/]{2,60})$#u', $s, $m)) return ['forHandle' => '@' . $m[1]];
        return null;
    }

    /** 채널 정보를 읽는다. 못 찾으면 null. 반환: channel_id·handle·title·description·thumbnail_url·banner_url·subscriber_count·video_count */
    public static function fetchChannel(array $q): ?array
    {
        $key = self::key();
        if (!$key) return null;
        $res = Http::timeout(15)->get(self::API . 'channels', array_merge($q, ['part' => 'snippet,statistics,brandingSettings', 'key' => $key]));
        if (!$res->ok()) return null;
        $it = $res->json('items.0');
        if (!$it) return null;
        $sn = $it['snippet'] ?? [];
        $st = $it['statistics'] ?? [];
        $thumbs = $sn['thumbnails'] ?? [];
        return [
            'channel_id' => $it['id'],
            'handle' => $sn['customUrl'] ?? null,
            'title' => mb_substr((string) ($sn['title'] ?? ''), 0, 150),
            'description' => mb_substr((string) ($sn['description'] ?? ''), 0, 2000),
            'thumbnail_url' => $thumbs['medium']['url'] ?? ($thumbs['default']['url'] ?? null),
            'banner_url' => $it['brandingSettings']['image']['bannerExternalUrl'] ?? null,
            'subscriber_count' => empty($st['hiddenSubscriberCount']) ? (int) ($st['subscriberCount'] ?? 0) : 0,
            'video_count' => (int) ($st['videoCount'] ?? 0),
        ];
    }

    /** ISO8601 길이(PT1M5S) → 초 */
    public static function seconds(string $iso): int
    {
        if (!preg_match('/^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $iso, $m)) return 0;
        return ((int) ($m[1] ?? 0)) * 86400 + ((int) ($m[2] ?? 0)) * 3600 + ((int) ($m[3] ?? 0)) * 60 + (int) ($m[4] ?? 0);
    }

    /** 채널 정보 + 최근 영상 $max 개를 새로 읽어 저장한다. 성공하면 true */
    public static function sync(YoutubeChannel $ch, int $max = 30): bool
    {
        $key = self::key();
        if (!$key) return false;

        $info = self::fetchChannel(['id' => $ch->channel_id]);
        if ($info) {
            $ch->fill(array_intersect_key($info, array_flip(['handle', 'title', 'description', 'thumbnail_url', 'banner_url', 'subscriber_count', 'video_count'])));
        }

        // 업로드 목록: 채널 번호의 UC 를 UU 로 바꾼 재생목록
        $uploads = 'UU' . substr($ch->channel_id, 2);
        $res = Http::timeout(15)->get(self::API . 'playlistItems', ['part' => 'snippet,contentDetails', 'playlistId' => $uploads, 'maxResults' => min(50, $max), 'key' => $key]);
        if (!$res->ok()) {
            $ch->last_synced_at = now();
            $ch->save();
            return false;
        }
        $items = $res->json('items') ?? [];
        $ids = [];
        foreach ($items as $it) {
            $vid = $it['contentDetails']['videoId'] ?? null;
            if ($vid) $ids[] = $vid;
        }
        $details = [];
        if ($ids) {
            $dr = Http::timeout(15)->get(self::API . 'videos', ['part' => 'contentDetails,statistics,status', 'id' => implode(',', $ids), 'key' => $key]);
            foreach (($dr->ok() ? ($dr->json('items') ?? []) : []) as $d) $details[$d['id']] = $d;
        }

        $latest = null;
        foreach ($items as $it) {
            $vid = $it['contentDetails']['videoId'] ?? null;
            $sn = $it['snippet'] ?? [];
            if (!$vid || !isset($details[$vid])) continue;                                    // 삭제·비공개 영상은 건너뜀
            if (($details[$vid]['status']['privacyStatus'] ?? 'public') !== 'public') continue;
            // 올린 사람이 '다른 사이트에서 재생 허용'을 꺼 둔 영상은 사이트에서 재생이 안 되므로 목록에서 뺀다(이미 저장된 것도 지움)
            if (($details[$vid]['status']['embeddable'] ?? true) === false) { YoutubeVideo::where('video_id', $vid)->delete(); continue; }
            $secs = self::seconds((string) ($details[$vid]['contentDetails']['duration'] ?? ''));
            $pub = $it['contentDetails']['videoPublishedAt'] ?? ($sn['publishedAt'] ?? null);
            $thumb = $sn['thumbnails']['medium']['url'] ?? ($sn['thumbnails']['default']['url'] ?? null);
            YoutubeVideo::updateOrCreate(['video_id' => $vid], [
                'youtube_channel_id' => $ch->id,
                'title' => mb_substr((string) ($sn['title'] ?? ''), 0, 300),
                'thumbnail_url' => $thumb,
                'duration_seconds' => $secs,
                'is_short' => $secs > 0 && $secs <= self::SHORT_MAX_SECONDS,
                'view_count' => (int) ($details[$vid]['statistics']['viewCount'] ?? 0),
                'published_at' => $pub ? \Carbon\Carbon::parse($pub) : null,
            ]);
            if ($pub && (!$latest || \Carbon\Carbon::parse($pub)->gt($latest))) $latest = \Carbon\Carbon::parse($pub);
        }
        if ($latest) $ch->latest_video_at = $latest;
        $ch->last_synced_at = now();
        $ch->save();
        return true;
    }
}
