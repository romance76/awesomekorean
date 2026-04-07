<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MusicTrack;
use App\Models\MusicCategory;
use Illuminate\Support\Facades\Http;

class FetchMusicTracks extends Command
{
    protected $signature = 'music:fetch {--daily=500} {--korean-ratio=70}';
    protected $description = '음악 트랙 자동 수집 (매일 500곡, 한국 70% + 팝 30%, 7일 롤링)';

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
        'ballad'  => ['pop ballad hits', 'best ballad songs', 'romantic ballads', 'slow songs 2024'],
        'trot'    => ['japanese enka', 'retro pop music', 'oldies music hits'],
        'kpop'    => ['pop music 2024', 'top 40 hits', 'pop music trending', 'Billboard hot 100'],
        'hiphop'  => ['hip hop hits 2024', 'rap music trending', 'hip hop playlist', 'best rap songs'],
        'rnb'     => ['R&B hits 2024', 'R&B playlist', 'best R&B songs', 'soul music'],
        'jazz'    => ['jazz music', 'jazz classics', 'smooth jazz', 'jazz cafe music'],
        'classic' => ['classical music', 'piano classical', 'orchestra famous', 'beethoven mozart'],
        'ost'     => ['movie soundtrack', 'film OST popular', 'anime OST', 'Hollywood soundtrack'],
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

        // 1단계: 7일 이상 된 트랙 삭제
        $deleted = MusicTrack::where('created_at', '<', now()->subDays(7))->delete();
        $this->info("🗑 7일 이상 된 트랙 {$deleted}곡 삭제");

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
                'videoDuration' => 'medium', // 4-20분 (duration 체크로 10분 초과 필터)
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

                // 10분(600초) 초과 필터링
                if ($seconds > 600) continue;
                // 10초 미만도 제외 (짧은 클립)
                if ($seconds > 0 && $seconds < 10) continue;

                if (MusicTrack::where('youtube_id', $videoId)->exists()) continue;
                if (mb_strlen($title) < 3) continue;
                if (preg_match('/live stream|라이브 방송|24\/7|radio|playlist|모음|메들리/i', $title)) continue;

                MusicTrack::create([
                    'category_id' => $categoryId,
                    'title' => mb_substr($title, 0, 200),
                    'artist' => mb_substr($channel, 0, 100),
                    'youtube_id' => $videoId,
                    'youtube_url' => "https://www.youtube.com/watch?v={$videoId}",
                    'duration' => $seconds,
                    'sort_order' => 0,
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
                        foreach ($response2->json('items', []) as $item2) {
                            if ($added >= $limit) break;
                            $vid = $item2['id']['videoId'] ?? null;
                            if (!$vid || MusicTrack::where('youtube_id', $vid)->exists()) continue;
                            $t = $item2['snippet']['title'] ?? '';
                            $c = $item2['snippet']['channelTitle'] ?? '';
                            if (mb_strlen($t) < 3 || preg_match('/live stream|라이브|24\/7/i', $t)) continue;

                            MusicTrack::create([
                                'category_id' => $categoryId,
                                'title' => mb_substr($t, 0, 200),
                                'artist' => mb_substr($c, 0, 100),
                                'youtube_id' => $vid,
                                'youtube_url' => "https://www.youtube.com/watch?v={$vid}",
                                'duration' => 0,
                                'sort_order' => 0,
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
        return (($m[1] ?? 0) * 3600) + (($m[2] ?? 0) * 60) + ($m[3] ?? 0);
    }
}
