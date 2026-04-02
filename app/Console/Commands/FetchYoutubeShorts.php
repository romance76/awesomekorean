<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Short;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FetchYoutubeShorts extends Command
{
    protected $signature = 'shorts:fetch {--limit=1000} {--days=30}';
    protected $description = 'Fetch Korean YouTube Shorts via YouTube Data API v3';

    private $searchQueries = [
        '한국 shorts', '먹방 shorts', '한식 요리', 'K-POP shorts',
        '한인 미국', '뷰티 메이크업', '한국 여행', '일상 브이로그 한국',
        '한국 코미디', 'Korean ASMR', '한국 뉴스', '운동 루틴',
        '한국 게임', 'Korean drama', '한국 패션', 'Korean food',
        'Korean cooking', 'Seoul vlog', 'Korea travel', 'BTS shorts',
        'BLACKPINK shorts', 'Korean beauty', 'Korean street food',
        '한국 카페', '한국 맛집', 'K-drama clips', '한국 음악',
        'Korean reaction', '한국 일상', 'Korean student',
    ];

    public function handle()
    {
        $limit = (int)$this->option('limit');
        $days = (int)$this->option('days');
        $apiKey = config('services.youtube.api_key') ?: env('YOUTUBE_API_KEY');

        if (!$apiKey) {
            $this->error('YOUTUBE_API_KEY not set in .env');
            return 1;
        }

        $this->info("Fetching up to {$limit} Korean YouTube Shorts via API...");

        // 1. Delete shorts older than N days
        $deleted = Short::where('platform', 'youtube')
            ->where('user_id', 1)
            ->where('created_at', '<', Carbon::now()->subDays($days))
            ->delete();
        if ($deleted) $this->info("Deleted {$deleted} shorts older than {$days} days");

        $added = 0;
        $skipped = 0;
        $apiCalls = 0;
        $queries = $this->searchQueries;
        shuffle($queries);

        foreach ($queries as $query) {
            if ($added >= $limit) break;
            if ($apiCalls >= 80) { $this->warn("API quota limit approaching, stopping."); break; }

            try {
                // Search for short videos
                $response = Http::timeout(10)->get('https://www.googleapis.com/youtube/v3/search', [
                    'key' => $apiKey,
                    'q' => $query,
                    'part' => 'snippet',
                    'type' => 'video',
                    'videoDuration' => 'short',
                    'order' => 'date',
                    'maxResults' => 50,
                    'relevanceLanguage' => 'ko',
                    'regionCode' => 'KR',
                ]);
                $apiCalls++;

                if (!$response->ok()) {
                    $this->warn("API error for '{$query}': " . $response->status());
                    continue;
                }

                $items = $response->json()['items'] ?? [];
                if (empty($items)) continue;

                // Get video IDs for duration check
                $videoIds = collect($items)->pluck('id.videoId')->filter()->implode(',');
                if (!$videoIds) continue;

                $detailResponse = Http::timeout(10)->get('https://www.googleapis.com/youtube/v3/videos', [
                    'key' => $apiKey,
                    'id' => $videoIds,
                    'part' => 'contentDetails,snippet',
                ]);
                $apiCalls++;

                if (!$detailResponse->ok()) continue;
                $videos = $detailResponse->json()['items'] ?? [];

                foreach ($videos as $video) {
                    if ($added >= $limit) break;

                    $videoId = $video['id'];
                    $title = $video['snippet']['title'] ?? '';
                    $channel = $video['snippet']['channelTitle'] ?? '';
                    $duration = $video['contentDetails']['duration'] ?? '';

                    // Parse duration (PT1M30S format) - only accept <= 60 seconds
                    $seconds = $this->parseDuration($duration);
                    if ($seconds > 60 || $seconds < 3) { $skipped++; continue; }

                    // Language filter
                    if ($this->hasBlockedLanguage($title) || $this->hasBlockedLanguage($channel)) {
                        $skipped++;
                        continue;
                    }

                    $url = "https://www.youtube.com/shorts/{$videoId}";
                    if (Short::where('url', $url)->exists()) continue;

                    $embedUrl = "https://www.youtube.com/embed/{$videoId}?autoplay=0&mute=0&controls=1&loop=1&playlist={$videoId}&rel=0";
                    $thumbnail = $video['snippet']['thumbnails']['high']['url']
                        ?? $video['snippet']['thumbnails']['medium']['url']
                        ?? "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg";

                    // Extract tags from title
                    $tags = [];
                    $tagMap = [
                        '요리' => '요리', '먹방' => '먹방', '뷰티' => '뷰티',
                        'K-POP' => 'K-POP', 'kpop' => 'K-POP', '여행' => '여행',
                        '코미디' => '코미디', 'ASMR' => 'ASMR', '뉴스' => '뉴스',
                        '운동' => '운동', '게임' => '게임', '음악' => '음악',
                        'food' => '요리', 'cook' => '요리', 'beauty' => '뷰티',
                        'travel' => '여행', 'vlog' => '일상', 'drama' => '드라마',
                    ];
                    foreach ($tagMap as $keyword => $tag) {
                        if (stripos($title . ' ' . $channel, $keyword) !== false) {
                            $tags[] = $tag;
                        }
                    }
                    $tags = array_unique($tags) ?: ['일반'];

                    Short::create([
                        'user_id' => 1,
                        'url' => $url,
                        'embed_url' => $embedUrl,
                        'platform' => 'youtube',
                        'title' => mb_substr($title, 0, 200),
                        'description' => mb_substr($channel, 0, 100),
                        'thumbnail' => $thumbnail,
                        'tags' => $tags,
                        'is_active' => true,
                    ]);
                    $added++;
                }
            } catch (\Exception $e) {
                $this->warn("Error for '{$query}': " . $e->getMessage());
                continue;
            }
        }

        $total = Short::where('platform', 'youtube')->count();
        $this->info("Done! Added: {$added}, Skipped: {$skipped}, API calls: {$apiCalls}, Total: {$total}");
    }

    private function parseDuration(string $duration): int
    {
        // PT1M30S, PT45S, PT1H2M3S
        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $m);
        return ((int)($m[1] ?? 0) * 3600) + ((int)($m[2] ?? 0) * 60) + (int)($m[3] ?? 0);
    }

    private function hasBlockedLanguage(string $text): bool
    {
        if (preg_match('/[\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}]/u', $text)) return true;
        if (preg_match('/[\x{3040}-\x{309f}\x{30a0}-\x{30ff}]/u', $text)) return true;
        if (preg_match('/[ăâđêôơưắấếốứ]/ui', $text)) return true;
        if (preg_match('/[\x{0e00}-\x{0e7f}]/u', $text)) return true;
        if (preg_match('/[\x{0600}-\x{06ff}]/u', $text)) return true;
        if (preg_match('/[\x{0900}-\x{097f}]/u', $text)) return true;
        if (preg_match('/[\x{0400}-\x{04ff}]/u', $text)) return true;
        if (preg_match('/[ñ¿¡]/u', $text)) return true;
        return false;
    }
}
