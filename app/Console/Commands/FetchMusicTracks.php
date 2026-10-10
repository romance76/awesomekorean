<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MusicTrack;
use App\Models\MusicCategory;
use Illuminate\Support\Facades\Http;

class FetchMusicTracks extends Command
{
    protected $signature = 'music:fetch {--daily=500} {--max-searches=0}';

    // YouTube search.list 는 1회당 쿼터 100 소모 → 1회 실행당 검색 횟수 상한을 두고,
    // 하루에 여러 번 나눠 실행해 쿼터(일 10,000) 안에서 꾸준히 쌓는다. 403/429 면 즉시 중단.
    private $searchBudget = 0;
    private $searchesUsed = 0;
    private $quotaHit = false;
    // 곡 길이 기준 — 기본은 2분30초~5분. 경음악(allow_any_length) 카테고리는 1분~30분으로 풀어서 수집
    private $minSec = 150;
    private $maxSec = 300;
    private $anyLen = false;
    // 공식 채널 우선(한국 가수·아이돌 카테고리). 경음악·채널 지정 카테고리는 해당 없음
    private $preferOfficial = false;
    private $strictJunk = false; // K-POP: 가사영상·커버·직캠 등 비공식 성격의 영상은 제외
    private const OFFICIAL_PREF_SLUGS = ['kpop', 'ballad', 'trot', 'hiphop', 'rnb', 'ost'];

    private function canSearch(): bool
    {
        return !$this->quotaHit && ($this->searchBudget <= 0 || $this->searchesUsed < $this->searchBudget);
    }

    private function ytSearch(array $params)
    {
        $this->searchesUsed++;
        $res = Http::get('https://www.googleapis.com/youtube/v3/search', $params);
        if (in_array($res->status(), [403, 429])) {
            $this->quotaHit = true;
            $this->warn("  ⚠ API 한도 도달({$res->status()}) — 이번 실행 중단, 다음 실행에서 이어서 수집");
        }
        return $res;
    }
    protected $description = '음악 트랙 자동 수집 (전 카테고리 한국 100%, 2분30초~5분, 30일 보관)';

    // 카테고리별 한국:미국 비율 (한국 %) — 전부 한국 100%
    private $ratios = [
        'ballad'  => 100,
        'trot'    => 100,
        'kpop'    => 100,
        'hiphop'  => 100,
        'rnb'     => 100,
        'jazz'    => 100,
        'classic' => 100,
        'ost'     => 100,
    ];

    // 카테고리별 한국 검색어
    private $koreanQueries = [
        'ballad'  => ['한국 발라드', '한국 발라드 명곡', '발라드 인기곡 2024', '발라드 인기곡 2025', '한국 발라드 노래', '감성 발라드', '슬픈 발라드'],
        'trot'    => ['트로트 인기곡', '트로트 명곡', '트로트 노래', '신나는 트로트', '트로트 메들리', '송가인', '임영웅 트로트'],
        'kpop'    => ['K-POP 인기곡', 'K-POP 신곡', 'BTS', 'BLACKPINK', 'NewJeans', 'aespa', 'SEVENTEEN', 'IVE', 'Stray Kids'],
        'hiphop'  => ['한국 힙합', '한국 랩', '쇼미더머니', 'Korean hip hop', '한국 힙합 명곡', '한국 래퍼'],
        'rnb'     => ['한국 R&B', 'Korean R&B', '한국 알앤비', 'K-R&B 인기곡', '딘 R&B', '크러쉬 노래'],
        'jazz'    => ['한국 재즈', 'Korean jazz', '재즈 카페 음악', '한국 재즈 보컬', '재즈 명곡'],
        'classic' => ['한국 클래식', 'Korean classical', '클래식 피아노', '클래식 명곡 연주'],
        'ost'     => ['한국 드라마 OST', 'K-drama OST', '드라마 OST 명곡', '영화 OST 한국', 'OST 인기곡 2024'],
    ];

    // 카테고리별 팝송 검색어
    private $popQueries = [
        'ballad'  => ['American pop ballad', 'Ed Sheeran ballad', 'Adele songs', 'Sam Smith songs', 'best English ballads'],
        'trot'    => ['oldies American music', 'Elvis Presley', '60s 70s pop hits', 'retro American pop'],
        'kpop'    => ['Billboard hot 100 official', 'US pop hits 2024', 'Taylor Swift', 'Dua Lipa', 'The Weeknd'],
        'hiphop'  => ['US hip hop hits', 'Drake songs', 'Kendrick Lamar', 'Eminem', 'American rap'],
        'rnb'     => ['American R&B', 'SZA songs', 'Daniel Caesar', 'US R&B hits', 'Frank Ocean'],
        'jazz'    => ['American jazz', 'jazz standards', 'smooth jazz USA', 'Norah Jones jazz'],
        'classic' => ['classical music performance', 'piano concerto famous', 'Beethoven symphony', 'Mozart piano', 'Vienna classical', 'Chopin nocturne'],
        'ost'     => ['Hollywood movie soundtrack', 'Disney OST', 'English movie theme song', 'American film score'],
    ];

    public function handle()
    {
        $dailyLimit = (int) $this->option('daily');
        $this->searchBudget = (int) $this->option('max-searches');

        $apiKey = config('services.youtube.api_key');
        if (!$apiKey) {
            // .env에서 직접 읽기
            $envPath = base_path('.env');
            if (file_exists($envPath)) {
                $envContent = file_get_contents($envPath);
                if (preg_match('/YOUTUBE_API_KEY=(.+)/', $envContent, $m)) {
                    $apiKey = trim($m[1]);
                }
            }
        }

        if (!$apiKey) {
            $this->error('YouTube API 키가 없습니다');
            return 1;
        }

        // auto_fetch=true 인 카테고리만 자동 수집 대상
        $categories = MusicCategory::where(function($q) {
            $q->where('auto_fetch', true)->orWhereNull('auto_fetch');
        })->get();
        // 곡이 적은 카테고리부터 수집 (검색 횟수 상한이 있어 뒤쪽 카테고리는 다음 실행 때 채워짐)
        $counts = MusicTrack::selectRaw('category_id, count(*) c')->groupBy('category_id')->pluck('c', 'category_id');
        $categories = $categories->sortBy(fn($c) => $counts[$c->id] ?? 0)->values();
        $skipped = MusicCategory::where('auto_fetch', false)->pluck('name')->all();
        if ($skipped) $this->info('⏭  자동 수집 제외: ' . implode(', ', $skipped));
        if ($categories->isEmpty()) {
            $this->error('자동 수집 대상 카테고리가 없습니다');
            return 1;
        }

        $this->info("=== 음악 트랙 자동 수집 시작 ===");
        $this->info("목표: {$dailyLimit}곡 (카테고리별 한국/미국 비율 차등)");

        // 1단계: 30일 이상 된 트랙 삭제 (유저 업로드 제외)
        $deleted = MusicTrack::where('created_at', '<', now()->subDays(30))
            ->where('is_user_submitted', false)
            ->delete();
        $this->info("🗑 30일 이상 된 시스템 트랙 {$deleted}곡 삭제");

        $totalAdded = 0;
        $perCategory = (int) ceil($dailyLimit / $categories->count());

        foreach ($categories as $cat) {
            if (!$this->canSearch()) { $this->info("⏹ 검색 {$this->searchesUsed}회 사용 — 이번 실행 종료"); break; }
            $this->anyLen = (bool) ($cat->allow_any_length ?? false);
            $this->minSec = $this->anyLen ? 60 : 150;
            $this->maxSec = $this->anyLen ? 1800 : 300;
            $this->preferOfficial = empty($cat->channel_url) && in_array($cat->slug, self::OFFICIAL_PREF_SLUGS, true);
            $this->strictJunk = ($cat->slug === 'kpop');
            // 관리자가 이 카테고리에 YouTube 채널을 직접 지정해둔 경우 — 일반
            // 키워드 검색 대신 그 채널의 업로드 영상만 가져옴. 사용자가 직접
            // 만든 카테고리("트로트새바람" 등)에 전혀 관련 없는 영상이 섞여
            // 들어가던 문제의 원인이 바로 이 구분이 없었던 것이었음.
            if (!empty($cat->channel_url)) {
                $this->info("\n📂 {$cat->name} ({$cat->slug}) - 채널 지정: {$cat->channel_url}");
                $added = $this->fetchFromChannel($apiKey, $cat->id, $cat->channel_url, $perCategory);
                $this->info("  ✅ 채널: {$added}곡 추가");
                $totalAdded += $added;
                continue;
            }

            $ratio = $this->ratios[$cat->slug] ?? 75;
            $koreanPerCat = (int) ceil($perCategory * $ratio / 100);
            $popPerCat = $perCategory - $koreanPerCat;
            $this->info("\n📂 {$cat->name} ({$cat->slug}) - 한국 {$koreanPerCat}곡({$ratio}%) + 팝 {$popPerCat}곡");

            // 한국 곡 수집 — DB 검색어 우선, 없으면 하드코딩 fallback
            $kQueries = $cat->korean_queries
                ? explode(',', $cat->korean_queries)
                : ($this->koreanQueries[$cat->slug] ?? ['한국 음악 ' . $cat->name]);
            $added = $this->fetchTracks($apiKey, $cat->id, $kQueries, $koreanPerCat);
            $this->info("  ✅ 한국: {$added}곡 추가");
            $totalAdded += $added;

            // 팝송 수집 — DB 검색어 우선
            $pQueries = $cat->pop_queries
                ? explode(',', $cat->pop_queries)
                : ($this->popQueries[$cat->slug] ?? ['pop music ' . $cat->slug]);
            if ($popPerCat > 0) {
                $added = $this->fetchTracks($apiKey, $cat->id, $pQueries, $popPerCat);
                $this->info("  ✅ 팝: {$added}곡 추가");
                $totalAdded += $added;
            }
        }

        $totalTracks = MusicTrack::count();
        $this->info("\n=== 완료: {$totalAdded}곡 추가, 총 {$totalTracks}곡 ===");

        return 0;
    }

    private function fetchTracks($apiKey, $categoryId, $queries, $limit)
    {
        $added = 0;
        $query = $queries[array_rand($queries)];
        $perPage = 50; // 검색 1회 비용(100)은 결과 개수와 무관 — 필터에서 걸러지는 몫을 감안해 항상 최대 50개를 받는다 (추가 곡 수는 $limit 으로 제한)

        try {
            if (!$this->canSearch()) return 0;
            $response = $this->ytSearch([
                'key' => $apiKey,
                'q' => $query . ($this->preferOfficial ? ' official MV' : ' music'),
                'type' => 'video',
                'videoCategoryId' => '10',
                // YouTube의 'medium'은 4~20분(포함)만 반환해 실제 목표 구간(2분30초~5분)의
                // 대부분(2:30~4:00, 대다수 K-pop/발라드 표준 곡 길이)이 API 단계에서부터
                // 걸러지고 있었음 — API 단에서는 필터링하지 않고 아래 contentDetails 기반
                // 로컬 duration 체크(150~300초)에만 맡기도록 수정.
                'videoDuration' => 'any',
                'part' => 'snippet',
                'maxResults' => $perPage,
                'order' => 'relevance',
                'publishedAfter' => now()->subYears(3)->toIso8601String(),
            ]);

            if (!$response->ok()) {
                $this->warn("  ⚠ API 오류: {$response->status()}");
                return 0;
            }

            $items = $this->officialFirst($response->json('items', []));
            $videoIds = collect($items)->pluck('id.videoId')->filter()->implode(',');

            // 영상 길이 + 재생 가능 여부 조회 (contentDetails, status)
            $durations = [];
            $blocked = []; // 다른 사이트에서 재생 불가(임베드 금지)·비공개 영상 → 저장하지 않음 (재생하면 "Video unavailable")
            if ($videoIds) {
                $detailRes = Http::get('https://www.googleapis.com/youtube/v3/videos', [
                    'key' => $apiKey,
                    'id' => $videoIds,
                    'part' => 'contentDetails,status',
                ]);
                if ($detailRes->ok()) {
                    foreach ($detailRes->json('items', []) as $v) {
                        $dur = $v['contentDetails']['duration'] ?? 'PT0S';
                        $durations[$v['id']] = $this->parseDuration($dur);
                        if (($v['status']['embeddable'] ?? true) === false || ($v['status']['privacyStatus'] ?? 'public') !== 'public') $blocked[$v['id']] = true;
                    }
                }
            }

            foreach ($items as $item) {
                if ($added >= $limit) break;

                $videoId = $item['id']['videoId'] ?? null;
                if (!$videoId) continue;

                $title = $item['snippet']['title'] ?? '';
                $channel = $item['snippet']['channelTitle'] ?? '';
                $seconds = $durations[$videoId] ?? 0;

                // duration 정보 필수
                if ($seconds <= 0) continue;
                // 기준 미만 제외 (숏츠/클립) — 기본 2분30초, 경음악 카테고리는 1분
                if ($seconds < $this->minSec) continue;
                if (!empty($blocked[$videoId])) continue;
                // 기준 초과 제외 (메들리/라이브) — 기본 5분, 경음악 카테고리는 30분
                if ($seconds > $this->maxSec) continue;

                if (MusicTrack::where('youtube_id', $videoId)->exists()) continue;
                if (mb_strlen($title) < 3) continue;
                if ($this->strictJunk && !$this->isOfficialChannel($channel) && preg_match(self::JUNK_TITLE_RE, $title)) continue;
                if (preg_match($this->anyLen ? '/live stream|라이브 방송|24\/7|radio/i' : '/live stream|라이브 방송|24\/7|radio|playlist|모음|메들리/i', $title)) continue;

                // ─── 한국어+영어 외 언어 필터 ───
                $text = $title . ' ' . $channel;
                // 일본어 (Hiragana + Katakana)
                if (preg_match('/[\x{3040}-\x{309F}]|[\x{30A0}-\x{30FF}]/u', $text)) continue;
                // 중국어 (한자 + 한국어 없음)
                if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $text) && !preg_match('/[\x{AC00}-\x{D7AF}]/u', $text)) continue;
                // 힌디/데바나가리/아랍/태국/벵골
                if (preg_match('/[\x{0900}-\x{097F}]|[\x{0600}-\x{06FF}]|[\x{0E00}-\x{0E7F}]|[\x{0980}-\x{09FF}]/u', $text)) continue;
                // 러시아어 등 키릴 문자
                if (preg_match('/[\x{0400}-\x{04FF}]/u', $text)) continue;
                // 베트남어 (확장 라틴 diacritics)
                if (preg_match('/[ăâđêôơưừứửữựắằẳẵặẻẽẹểễệốồổỗộớờởỡợýỷỹỵ]/u', $text)) continue;
                // 스페인어 diacritics/punctuation
                if (preg_match('/[áéíóúñ¿¡]/u', $text)) continue;
                // 언어/문화권 키워드
                if (preg_match('/Bollywood|Hindi|Tamil|Telugu|Punjabi|Arabic|Thai|Türk|Indo|Tagalog|Malay|Khmer|Chinese|Japanese|Mandarin|Cantonese|Vietnamese|Việt|中文|日本語|ภาษาไทย|Tiếng Việt|Myanmar|Lao|Cambodian|Filipino|Bahasa|Russian|Русский|по-русски/i', $text)) continue;
                // 인도 지역어 및 해시태그
                if (preg_match('/Haryanvi|Haryana|Bhojpuri|Marathi|Gujarati|Bengali|Kannada|Malayalam|Urdu|Sindhi|Nepali|Sinhala|Desi|#haryanvi|#bhojpuri|#desi|#bollywood|#hindi/i', $text)) continue;
                // 인도 로마자 흔한 단어
                if (preg_match('/\b(hamare|tumhare|tumko|mujhko|kya|hai|kaise|kyun|nahi|nahin|bhai|dost|acha|achha|theek|bilkul|zaroor|shaadi|khushi|pyaar|ladka|ladki|bhaiya|didi|mera|meri|tera|teri|wala|wali|gaana|gaya|gayi|chalo|dekho|suno|bahut|thoda|zyada|kaam|ghar|log|dil|zindagi|mohabbat|ishq)\b/i', $text)) continue;
                // 스페인어 로마자
                if (preg_match('/\b(hola|gracias|por favor|amigo|amiga|hermano|hermana|cómo|como|qué|que|cuando|donde|porque|muy|mucho|poco|bueno|buena|malo|mala|grande|pequeño|dejan|hijo|hija|novio|novia|corazón|corazon|fiesta|gente|vida|amor)\b/i', $text)) continue;
                if (preg_match('/español|castellano|México|Mexico|Argentina|España|Espana|Latino|Reggaeton|Bachata|Cumbia|Salsa/i', $text)) continue;

                MusicTrack::create([
                    'category_id' => $categoryId,
                    'title' => mb_substr(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, 200),
                    'artist' => mb_substr($channel, 0, 100),
                    'youtube_id' => $videoId,
                    'youtube_url' => "https://www.youtube.com/watch?v={$videoId}",
                    'duration' => $seconds,
                    'sort_order' => 0,
                    'is_user_submitted' => false,
                ]);

                $added++;
            }

            // limit에 못 미치면 추가 쿼리로 보충
            if ($added < $limit && count($queries) > 1 && $this->canSearch()) {
                $remainQueries = array_diff($queries, [$query]);
                if (!empty($remainQueries)) {
                    $nextQuery = $remainQueries[array_rand($remainQueries)];
                    $remain = $limit - $added;

                    $response2 = $this->ytSearch([
                        'key' => $apiKey,
                        'q' => $nextQuery . ($this->preferOfficial ? ' official MV' : ' music'),
                        'type' => 'video',
                        'videoCategoryId' => '10',
                        'part' => 'snippet',
                        'maxResults' => min($remain, 50),
                        'order' => 'date',
                    ]);

                    if ($response2->ok()) {
                        // fallback 블록도 duration 조회 및 언어 필터 적용
                        $fallbackIds = collect($response2->json('items', []))->pluck('id.videoId')->filter()->implode(',');
                        $fbDurations = [];
                        $fbBlocked = [];
                        if ($fallbackIds) {
                            $fbDetail = Http::get('https://www.googleapis.com/youtube/v3/videos', [
                                'key' => $apiKey, 'id' => $fallbackIds, 'part' => 'contentDetails,status',
                            ]);
                            if ($fbDetail->ok()) {
                                foreach ($fbDetail->json('items', []) as $v) {
                                    $fbDurations[$v['id']] = $this->parseDuration($v['contentDetails']['duration'] ?? 'PT0S');
                                    if (($v['status']['embeddable'] ?? true) === false || ($v['status']['privacyStatus'] ?? 'public') !== 'public') $fbBlocked[$v['id']] = true;
                                }
                            }
                        }

                        foreach ($this->officialFirst($response2->json('items', [])) as $item2) {
                            if ($added >= $limit) break;
                            $vid = $item2['id']['videoId'] ?? null;
                            if (!$vid || MusicTrack::where('youtube_id', $vid)->exists()) continue;
                            $t = $item2['snippet']['title'] ?? '';
                            $c = $item2['snippet']['channelTitle'] ?? '';
                            $sec2 = $fbDurations[$vid] ?? 0;

                            // duration 필수 + 2분30초(150초) 이상 5분 이하 (예전엔 10초 이상이라 짧은 곡이 들어왔음)
                            if ($sec2 <= 0 || $sec2 > $this->maxSec || $sec2 < $this->minSec) continue;
                            if (!empty($fbBlocked[$vid])) continue;
                            if (mb_strlen($t) < 3 || preg_match('/live stream|라이브|24\/7/i', $t)) continue;

                            // 언어 필터
                            $fbText = $t . ' ' . $c;
                            if (preg_match('/[\x{3040}-\x{309F}]|[\x{30A0}-\x{30FF}]/u', $fbText)) continue;
                            if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $fbText) && !preg_match('/[\x{AC00}-\x{D7AF}]/u', $fbText)) continue;
                            if (preg_match('/[\x{0900}-\x{097F}]|[\x{0600}-\x{06FF}]|[\x{0E00}-\x{0E7F}]|[\x{0980}-\x{09FF}]/u', $fbText)) continue;
                            if (preg_match('/[\x{0400}-\x{04FF}]/u', $fbText)) continue;
                            if (preg_match('/[ăâđêôơưừứửữựắằẳẵặẻẽẹểễệốồổỗộớờởỡợýỷỹỵ]/u', $fbText)) continue;
                            if (preg_match('/[áéíóúñ¿¡]/u', $fbText)) continue;
                            if (preg_match('/Bollywood|Hindi|Tamil|Telugu|Punjabi|Arabic|Thai|Türk|Indo|Tagalog|Malay|Khmer|Chinese|Japanese|Mandarin|Cantonese|Vietnamese|Việt|中文|日本語|Myanmar|Lao|Cambodian|Filipino|Bahasa|español|castellano|México|Argentina|España|Latino|Reggaeton|Bachata|Russian|Русский/i', $fbText)) continue;

                            MusicTrack::create([
                                'category_id' => $categoryId,
                                'title' => mb_substr($t, 0, 200),
                                'artist' => mb_substr($c, 0, 100),
                                'youtube_id' => $vid,
                                'youtube_url' => "https://www.youtube.com/watch?v={$vid}",
                                'duration' => $sec2,
                                'sort_order' => 0,
                                'is_user_submitted' => false,
                            ]);
                            $added++;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->warn("  ⚠ 수집 실패: " . $e->getMessage());
        }

        return $added;
    }

    // 카테고리에 지정된 YouTube 채널의 최근 업로드만 수집 (키워드 검색 없음) —
    // 관리자가 "이 채널만" 원하는 카테고리(트로트새바람 등)용.
    private function fetchFromChannel($apiKey, $categoryId, $channelUrl, $limit)
    {
        $added = 0;
        try {
            $resolver = app(\App\Services\YoutubeChannelResolver::class);
            $channelId = $resolver->resolveChannelId($apiKey, $channelUrl);
            if (!$channelId) {
                $this->warn("  ⚠ 채널을 찾을 수 없음: {$channelUrl}");
                return 0;
            }
            // 필터링으로 걸러질 것을 감안해 목표치보다 넉넉히 가져옴
            $videoIds = $resolver->fetchChannelVideos($apiKey, $channelId, max($limit * 4, 50));
            $videoIds = array_slice($videoIds, 0, 200);
            if (empty($videoIds)) return 0;

            $durations = [];
            foreach (array_chunk($videoIds, 50) as $chunk) {
                $detailRes = \Illuminate\Support\Facades\Http::get('https://www.googleapis.com/youtube/v3/videos', [
                    'key' => $apiKey, 'id' => implode(',', $chunk), 'part' => 'snippet,contentDetails,status',
                ]);
                if (!$detailRes->ok()) continue;

                foreach ($detailRes->json('items', []) as $v) {
                    if ($added >= $limit) break 2;

                    $videoId = $v['id'] ?? null;
                    if (!$videoId) continue;

                    $title = $v['snippet']['title'] ?? '';
                    $channel = $v['snippet']['channelTitle'] ?? '';
                    $seconds = $this->parseDuration($v['contentDetails']['duration'] ?? 'PT0S');

                    if ($seconds < $this->minSec || $seconds > $this->maxSec) continue;
                    if (($v['status']['embeddable'] ?? true) === false || ($v['status']['privacyStatus'] ?? 'public') !== 'public') continue;
                    if (MusicTrack::where('youtube_id', $videoId)->exists()) continue;
                    if (mb_strlen($title) < 3) continue;
                    if (preg_match('/live stream|라이브 방송|24\/7|radio|playlist|모음|메들리/i', $title)) continue;

                    MusicTrack::create([
                        'category_id' => $categoryId,
                        'title' => mb_substr(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, 200),
                        'artist' => mb_substr($channel, 0, 100),
                        'youtube_id' => $videoId,
                        'youtube_url' => "https://www.youtube.com/watch?v={$videoId}",
                        'duration' => $seconds,
                        'sort_order' => 0,
                        'is_user_submitted' => false,
                    ]);
                    $added++;
                }
            }
        } catch (\Exception $e) {
            $this->warn("  ⚠ 채널 수집 실패: " . $e->getMessage());
        }

        return $added;
    }

    // ─── 공식 채널 우선 ───
    // 한국 가수·아이돌 곡은 가능하면 소속사/방송사/공식 레이블 채널의 영상을 가져온다.
    // YouTube API 는 "공식 여부"를 직접 알려주지 않아서, 알려진 공식 채널 이름 + 공식 영상에 흔한 표기로 판별한다.
    // (추가 API 비용 없음 — 이미 받아온 검색 결과의 순서만 바꾼다)
    private const OFFICIAL_CHANNEL_RE = '/HYBE|BIGHIT|BANGTAN|SMTOWN|SM Entertainment|JYP Entertainment|JYP|YG ENTERTAINMENT|YG\s?ENTERTAINMENT|STARSHIP|Starship|CUBE|FNC|Pledis|PLEDIS|Woollim|WOOLLIM|ADOR|BELIFT|SWING|Source Music|KOZ|MLD|1theK|원더케이|Stone Music|스톤뮤직|Genie Music|지니뮤직|KAKAO ?M|카카오엠|Melon|멜론|Mnet|M2|KBS|MBC|SBS|JTBC|tvN|Dingo|딩고|Studio Choom|스튜디오 춤|Show Champion|뮤직뱅크|인기가요|엠카운트다운|엔터테인먼트|Entertainment|Records|레코드|Official|OFFICIAL|VEVO|Vevo|- Topic|뮤직$/u';
    private const JUNK_TITLE_RE = '/lyrics|가사|cover|커버|fancam|직캠|reaction|리액션|nightcore|slowed|reverb|8d audio|karaoke|노래방|ai cover|ai music|#shorts|shorts|playlist|모음|mix\b|compilation/i';

    private function isOfficialChannel(string $channel): bool
    {
        return (bool) preg_match(self::OFFICIAL_CHANNEL_RE, $channel);
    }

    // 검색 결과를 공식 채널 우선으로 정렬(같은 그룹 안에서는 원래 순서 유지)
    private function officialFirst(array $items): array
    {
        if (!$this->preferOfficial) return $items;
        $keyed = [];
        foreach ($items as $i => $it) {
            $ch = $it['snippet']['channelTitle'] ?? '';
            $keyed[] = [$this->isOfficialChannel($ch) ? 0 : 1, $i, $it];
        }
        usort($keyed, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        return array_map(fn ($k) => $k[2], $keyed);
    }
    // ISO 8601 duration → 초 변환 (PT3M45S → 225)
    private function parseDuration($iso)
    {
        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $iso, $m);
        return (intval($m[1] ?? 0) * 3600) + (intval($m[2] ?? 0) * 60) + intval($m[3] ?? 0);
    }
}
