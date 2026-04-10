<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MusicTrack;
use App\Models\MusicCategory;
use Illuminate\Support\Facades\Http;

class FetchMusicTracks extends Command
{
    protected $signature = 'music:fetch {--daily=500} {--korean-ratio=75}';
    protected $description = '음악 트랙 자동 수집 (매일 500곡, 한국 75% + 팝 25%, 5분 미만, 7일 롤링, 유저 업로드 제외)';

    // 카테고리별 한국 검색어
    private $koreanQueries = [
        'ballad'  => ['한국 발라드', '한국 발라드 명곡', '발라드 인기곡 2024', '발라드 인기곡 2025', '한국 발라드 노래', '감성 발라드', '슬픈 발라드'],
        'trot'    => ['트로트 인기곡', '트로트 명곡', '트로트 노래', '신나는 트로트', '트로트 메들리', '송가인', '임영웅 트로트'],
        'kpop'    => ['K-POP 인기곡', 'K-POP 신곡', 'BTS', 'BLACKPINK', 'NewJeans', 'aespa', 'SEVENTEEN', 'IVE', 'Stray Kids'],
        'hiphop'  => ['한국 힙합', '한국 랩', '쇼미더머니', 'Korean hip hop', '한국 힙합 명곡', '한국 래퍼'],
        'rnb'     => ['한국 R&B', 'Korean R&B', '한국 알앤비', 'K-R&B 인기곡', '딘 R&B', '크러쉬 노래'],
        'jazz'    => ['한국 재즈', 'Korean jazz', '재즈 카페 음악', '한국 재즈 보컬', '재즈 명곡'],
        'classic' => ['한국 클래식', 'Korean classical', '클래식 피아노', '클래식 명곡 연주', '한국 클래식 연주'],
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
        'classic' => ['classical music performance', 'piano concerto famous', 'London symphony', 'classical masterpieces'],
        'ost'     => ['Hollywood movie soundtrack', 'Disney OST', 'English movie theme song', 'American film score'],
    ];

    public function handle()
    {
        $dailyLimit = (int) $this->option('daily');
        $koreanRatio = (int) $this->option('korean-ratio');
        $koreanCount = (int) ($dailyLimit * $koreanRatio / 100);
        $popCount = $dailyLimit - $koreanCount;

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

        $categories = MusicCategory::all();
        if ($categories->isEmpty()) {
            $this->error('음악 카테고리가 없습니다');
            return 1;
        }

        $this->info("=== 음악 트랙 자동 수집 시작 ===");
        $this->info("목표: {$dailyLimit}곡 (한국 {$koreanCount} + 팝 {$popCount})");

        // 1단계: 7일 이상 된 트랙 삭제 (단, 유저 업로드 트랙은 유지)
        $deleted = MusicTrack::where('created_at', '<', now()->subDays(7))
            ->where('is_user_submitted', false)
            ->delete();
        $this->info("🗑 7일 이상 된 시스템 트랙 {$deleted}곡 삭제 (유저 업로드 제외)");

        $totalAdded = 0;
        $perCategory = (int) ceil($dailyLimit / $categories->count());
        $koreanPerCat = (int) ceil($koreanCount / $categories->count());
        $popPerCat = $perCategory - $koreanPerCat;

        foreach ($categories as $cat) {
            $this->info("\n📂 {$cat->name} ({$cat->slug}) - 한국 {$koreanPerCat}곡 + 팝 {$popPerCat}곡");

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
            $added = $this->fetchTracks($apiKey, $cat->id, $pQueries, $popPerCat);
            $this->info("  ✅ 팝: {$added}곡 추가");
            $totalAdded += $added;
        }

        $totalTracks = MusicTrack::count();
        $this->info("\n=== 완료: {$totalAdded}곡 추가, 총 {$totalTracks}곡 ===");

        return 0;
    }

    private function fetchTracks($apiKey, $categoryId, $queries, $limit)
    {
        $added = 0;
        $query = $queries[array_rand($queries)];
        $perPage = min($limit, 50); // YouTube API 최대 50

        try {
            $response = Http::get('https://www.googleapis.com/youtube/v3/search', [
                'key' => $apiKey,
                'q' => $query . ' music',
                'type' => 'video',
                'videoCategoryId' => '10',
                'videoDuration' => 'short', // YouTube 기준 < 4분 (5분 미만 보장에 더 적합)
                'part' => 'snippet',
                'maxResults' => $perPage,
                'order' => 'relevance',
                'publishedAfter' => now()->subYears(3)->toIso8601String(),
            ]);

            if (!$response->ok()) {
                $this->warn("  ⚠ API 오류: {$response->status()}");
                return 0;
            }

            $items = $response->json('items', []);
            $videoIds = collect($items)->pluck('id.videoId')->filter()->implode(',');

            // 영상 길이 조회 (contentDetails)
            $durations = [];
            if ($videoIds) {
                $detailRes = Http::get('https://www.googleapis.com/youtube/v3/videos', [
                    'key' => $apiKey,
                    'id' => $videoIds,
                    'part' => 'contentDetails',
                ]);
                if ($detailRes->ok()) {
                    foreach ($detailRes->json('items', []) as $v) {
                        $dur = $v['contentDetails']['duration'] ?? 'PT0S';
                        $durations[$v['id']] = $this->parseDuration($dur);
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

                // 5분(300초) 초과 필터링
                if ($seconds > 300) continue;
                // 10초 미만도 제외 (짧은 클립)
                if ($seconds > 0 && $seconds < 10) continue;

                if (MusicTrack::where('youtube_id', $videoId)->exists()) continue;
                if (mb_strlen($title) < 3) continue;
                if (preg_match('/live stream|라이브 방송|24\/7|radio|playlist|모음|메들리/i', $title)) continue;

                // ─── 한국어+영어 외 언어 필터 ───
                $text = $title . ' ' . $channel;
                // 일본어 (Hiragana + Katakana)
                if (preg_match('/[\x{3040}-\x{309F}]|[\x{30A0}-\x{30FF}]/u', $text)) continue;
                // 중국어 (한자 + 한국어 없음)
                if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $text) && !preg_match('/[\x{AC00}-\x{D7AF}]/u', $text)) continue;
                // 힌디/데바나가리/아랍/태국/벵골
                if (preg_match('/[\x{0900}-\x{097F}]|[\x{0600}-\x{06FF}]|[\x{0E00}-\x{0E7F}]|[\x{0980}-\x{09FF}]/u', $text)) continue;
                // 베트남어 (확장 라틴 diacritics)
                if (preg_match('/[ăâđêôơưừứửữựắằẳẵặẻẽẹểễệốồổỗộớờởỡợýỷỹỵ]/u', $text)) continue;
                // 스페인어 diacritics/punctuation
                if (preg_match('/[áéíóúñ¿¡]/u', $text)) continue;
                // 언어/문화권 키워드
                if (preg_match('/Bollywood|Hindi|Tamil|Telugu|Punjabi|Arabic|Thai|Türk|Indo|Tagalog|Malay|Khmer|Chinese|Japanese|Mandarin|Cantonese|Vietnamese|Việt|中文|日本語|ภาษาไทย|Tiếng Việt|Myanmar|Lao|Cambodian|Filipino|Bahasa/i', $text)) continue;
                if (preg_match('/español|castellano|México|Mexico|Argentina|España|Espana|Latino|Reggaeton|Bachata|Cumbia|Salsa/i', $text)) continue;

                MusicTrack::create([
                    'category_id' => $categoryId,
                    'title' => mb_substr($title, 0, 200),
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
            if ($added < $limit && count($queries) > 1) {
                $remainQueries = array_diff($queries, [$query]);
                if (!empty($remainQueries)) {
                    $nextQuery = $remainQueries[array_rand($remainQueries)];
                    $remain = $limit - $added;

                    $response2 = Http::get('https://www.googleapis.com/youtube/v3/search', [
                        'key' => $apiKey,
                        'q' => $nextQuery . ' music',
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
                        if ($fallbackIds) {
                            $fbDetail = Http::get('https://www.googleapis.com/youtube/v3/videos', [
                                'key' => $apiKey, 'id' => $fallbackIds, 'part' => 'contentDetails',
                            ]);
                            if ($fbDetail->ok()) {
                                foreach ($fbDetail->json('items', []) as $v) {
                                    $fbDurations[$v['id']] = $this->parseDuration($v['contentDetails']['duration'] ?? 'PT0S');
                                }
                            }
                        }

                        foreach ($response2->json('items', []) as $item2) {
                            if ($added >= $limit) break;
                            $vid = $item2['id']['videoId'] ?? null;
                            if (!$vid || MusicTrack::where('youtube_id', $vid)->exists()) continue;
                            $t = $item2['snippet']['title'] ?? '';
                            $c = $item2['snippet']['channelTitle'] ?? '';
                            $sec2 = $fbDurations[$vid] ?? 0;

                            // 5분 초과 제외
                            if ($sec2 > 300 || ($sec2 > 0 && $sec2 < 10)) continue;
                            if (mb_strlen($t) < 3 || preg_match('/live stream|라이브|24\/7/i', $t)) continue;

                            // 언어 필터
                            $fbText = $t . ' ' . $c;
                            if (preg_match('/[\x{3040}-\x{309F}]|[\x{30A0}-\x{30FF}]/u', $fbText)) continue;
                            if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $fbText) && !preg_match('/[\x{AC00}-\x{D7AF}]/u', $fbText)) continue;
                            if (preg_match('/[\x{0900}-\x{097F}]|[\x{0600}-\x{06FF}]|[\x{0E00}-\x{0E7F}]|[\x{0980}-\x{09FF}]/u', $fbText)) continue;
                            if (preg_match('/[ăâđêôơưừứửữựắằẳẵặẻẽẹểễệốồổỗộớờởỡợýỷỹỵ]/u', $fbText)) continue;
                            if (preg_match('/[áéíóúñ¿¡]/u', $fbText)) continue;
                            if (preg_match('/Bollywood|Hindi|Tamil|Telugu|Punjabi|Arabic|Thai|Türk|Indo|Tagalog|Malay|Khmer|Chinese|Japanese|Mandarin|Cantonese|Vietnamese|Việt|中文|日本語|Myanmar|Lao|Cambodian|Filipino|Bahasa|español|castellano|México|Argentina|España|Latino|Reggaeton|Bachata/i', $fbText)) continue;

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

    // ISO 8601 duration → 초 변환 (PT3M45S → 225)
    private function parseDuration($iso)
    {
        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $iso, $m);
        return (intval($m[1] ?? 0) * 3600) + (intval($m[2] ?? 0) * 60) + intval($m[3] ?? 0);
    }
}
