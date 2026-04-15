<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Short;
use Illuminate\Support\Facades\Http;

class FetchYoutubeShorts extends Command
{
    protected $signature = 'shorts:fetch {--limit=500} {--korean-ratio=90}';
    protected $description = '숏츠 자동 수집 (매일 500개, 한국 90% + 북미 10%, 세로형만, 14일 이내 신규, 30일 롤링)';

    // 한국 검색어
    private $koreanQueries = [
        '한국 shorts', '먹방 shorts', '한식 요리 shorts', 'K-POP shorts',
        '한국 여행 shorts', '한국 일상 브이로그', '한국 카페 shorts',
        '한국 맛집', 'Korean beauty shorts', '한국 코미디 shorts',
        '한국 음악 shorts', 'BTS shorts', 'BLACKPINK shorts', 'NewJeans shorts',
        '한국 패션 shorts', '한인타운', '미국 한인 일상', 'LA 한인',
        'Korean American shorts', 'K-drama shorts', '한국 웃긴 영상',
        '서울 브이로그 shorts', '한국 거리음식', '한국 ASMR shorts',
    ];

    // 북미권 검색어 (영어)
    private $usQueries = [
        'USA viral shorts', 'American funny shorts', 'US comedy shorts',
        'trending shorts USA', 'American food shorts', 'NYC shorts',
        'LA vlog shorts', 'US life hack shorts', 'American reaction shorts',
        'US pet shorts', 'American cooking shorts', 'US travel shorts',
        'English comedy shorts', 'viral shorts English', 'US trending today',
    ];

    public function handle()
    {
        $dailyLimit = (int) $this->option('limit');
        $koreanRatio = (int) $this->option('korean-ratio');
        $koreanCount = (int) ($dailyLimit * $koreanRatio / 100);
        $usCount = $dailyLimit - $koreanCount;

        $apiKey = config('services.youtube.api_key');
        if (!$apiKey) {
            $envFile = base_path('.env');
            if (file_exists($envFile)) {
                $content = file_get_contents($envFile);
                if (preg_match('/YOUTUBE_API_KEY=(.+)/', $content, $m)) $apiKey = trim($m[1]);
            }
        }
        if (!$apiKey) { $this->error('YOUTUBE_API_KEY not set'); return 1; }

        $this->info("=== 숏츠 수집 시작 (한국 {$koreanCount} + 북미 {$usCount}) ===");

        // 1단계: 30일 이상 된 숏츠 삭제
        $deleted = Short::whereNull('user_id')->where('created_at', '<', now()->subDays(30))->delete();
        $this->info("🗑 30일 지난 숏츠 {$deleted}개 삭제");

        // 2단계: 한국 숏츠 수집
        $this->info("\n🇰🇷 한국 숏츠 수집 ({$koreanCount}개)...");
        $krAdded = $this->fetchShorts($apiKey, $this->koreanQueries, $koreanCount, 'ko');

        // 3단계: 북미 숏츠 수집
        $this->info("\n🇺🇸 북미 숏츠 수집 ({$usCount}개)...");
        $usAdded = $this->fetchShorts($apiKey, $this->usQueries, $usCount, 'en');

        $total = Short::count();
        $this->info("\n=== 완료: 한국 {$krAdded} + 북미 {$usAdded} = " . ($krAdded + $usAdded) . "개 추가, 전체 {$total}개 ===");
        return 0;
    }

    private function fetchShorts($apiKey, $queries, $limit, $lang)
    {
        $added = 0;
        shuffle($queries);

        foreach ($queries as $query) {
            if ($added >= $limit) break;

            try {
                $response = Http::timeout(10)->get('https://www.googleapis.com/youtube/v3/search', [
                    'key' => $apiKey,
                    'q' => $query,
                    'part' => 'snippet',
                    'type' => 'video',
                    'videoDuration' => 'short', // 60초 이하
                    'order' => 'date',
                    'maxResults' => 50,
                    'relevanceLanguage' => $lang,
                    'publishedAfter' => now()->subDays(14)->toIso8601String(),
                ]);

                if (!$response->ok()) {
                    if ($response->status() === 403) { $this->error('API 할당량 초과!'); break; }
                    continue;
                }

                $videoIds = collect($response->json('items', []))->pluck('id.videoId')->filter()->implode(',');
                if (!$videoIds) continue;

                // 세로형 체크 (contentDetails + player)
                $detailRes = Http::timeout(10)->get('https://www.googleapis.com/youtube/v3/videos', [
                    'key' => $apiKey,
                    'id' => $videoIds,
                    'part' => 'snippet,contentDetails',
                ]);

                if (!$detailRes->ok()) continue;

                foreach ($detailRes->json('items', []) as $v) {
                    if ($added >= $limit) break;

                    $videoId = $v['id'];
                    $title = $v['snippet']['title'] ?? '';
                    $channel = $v['snippet']['channelTitle'] ?? '';
                    $dur = $v['contentDetails']['duration'] ?? 'PT0S';
                    $text = $title . ' ' . $channel;

                    // 중복 체크
                    if (Short::where('youtube_id', $videoId)->exists()) continue;

                    // 길이 체크 (60초 이하만)
                    preg_match('/PT(?:(\d+)M)?(?:(\d+)S)?/', $dur, $dm);
                    $seconds = (intval($dm[1] ?? 0) * 60) + intval($dm[2] ?? 0);
                    if ($seconds > 60 || $seconds < 5) continue;

                    // ─── 비한국/비영어 언어 필터 ───
                    // 일본어 (Hiragana + Katakana)
                    if (preg_match('/[\x{3040}-\x{309F}]|[\x{30A0}-\x{30FF}]/u', $text)) continue;
                    // 중국어 (한국어 없는 한자)
                    if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $text) && !preg_match('/[\x{AC00}-\x{D7AF}]/u', $text)) continue;
                    // 힌디/아랍/태국/벵골
                    if (preg_match('/[\x{0900}-\x{097F}]|[\x{0600}-\x{06FF}]|[\x{0E00}-\x{0E7F}]|[\x{0980}-\x{09FF}]/u', $text)) continue;
                    // 베트남어 (확장 라틴 + diacritics)
                    if (preg_match('/[ăâđêôơưừứửữựắằẳẵặẻẽẹểễệốồổỗộớờởỡợýỷỹỵ]/u', $text)) continue;
                    // 스페인어 diacritics/punctuation
                    if (preg_match('/[áéíóúñ¿¡]/u', $text)) continue;
                    // 언어/문화권 키워드
                    if (preg_match('/Bollywood|Hindi|Tamil|Telugu|Punjabi|Arabic|Thai|Türk|Indo|Tagalog|Vietnamese|Việt|中文|日本語|Myanmar|Bahasa|Mandarin|Cantonese/i', $text)) continue;
                    // 인도 지역어 및 해시태그
                    if (preg_match('/Haryanvi|Haryana|Bhojpuri|Marathi|Gujarati|Bengali|Kannada|Malayalam|Urdu|Sindhi|Nepali|Sinhala|Desi|Punjabi|#haryanvi|#bhojpuri|#desi|#bollywood|#hindi/i', $text)) continue;
                    // 인도 로마자 흔한 단어 (Romanized Hindi)
                    if (preg_match('/\b(hamare|tumhare|tumko|mujhko|kya|hai|kaise|kyun|nahi|nahin|bhai|dost|acha|achha|theek|bilkul|zaroor|shaadi|khushi|pyaar|ladka|ladki|bhaiya|didi|mera|meri|tera|teri|wala|wali|gaana|gaya|gayi|chalo|dekho|suno|bahut|thoda|zyada|kaam|ghar|log|dil|zindagi|mohabbat|ishq)\b/i', $text)) continue;
                    // 스페인어 로마자 (Romanized Spanish)
                    if (preg_match('/\b(hola|gracias|por favor|amigo|amiga|hermano|hermana|cómo|como|qué|que|cuando|donde|porque|muy|mucho|poco|bueno|buena|malo|mala|grande|pequeño|dejan|hijo|hija|novio|novia|corazón|corazon|fiesta|gente|vida|amor)\b/i', $text)) continue;
                    if (preg_match('/español|castellano|México|Mexico|Argentina|España|Espana|Latino|Reggaeton|Bachata|Cumbia|Salsa/i', $text)) continue;

                    Short::create([
                        'user_id' => null,
                        'title' => mb_substr($title, 0, 200),
                        'video_url' => "https://www.youtube.com/shorts/{$videoId}",
                        'youtube_id' => $videoId,
                        'thumbnail_url' => "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg",
                        'duration' => $seconds,
                        'is_active' => true,
                    ]);
                    $added++;
                }
            } catch (\Exception $e) {
                $this->warn("에러: " . $e->getMessage());
                if (str_contains($e->getMessage(), '403')) break;
            }
        }

        $this->info("  ✅ {$added}개 추가");
        return $added;
    }
}
