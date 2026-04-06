<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Short;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class FetchYoutubeShorts extends Command
{
    protected $signature = 'shorts:fetch {--limit=50} {--days=7}';
    protected $description = '한인 관련 YouTube Shorts 자동 수집 (매일 실행)';

    private $searchQueries = [
        '한국 shorts', '먹방 shorts', '한식 요리 shorts', 'K-POP shorts',
        '한인 미국 shorts', '한국 여행 shorts', '한국 일상 브이로그',
        'Korean food shorts', 'Korean cooking', 'Seoul vlog shorts',
        '한국 카페 shorts', '한국 맛집', 'Korean beauty shorts',
        '한국 코미디 shorts', '한국 음악 shorts', 'BTS shorts',
        'Korean street food', '한국 패션 shorts', '한인타운',
        '미국 한인 일상', 'LA 한인', 'Korean American',
    ];

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $days = (int) $this->option('days');
        $apiKey = env('YOUTUBE_API_KEY');

        if (!$apiKey) {
            $this->error('YOUTUBE_API_KEY not set');
            return 1;
        }

        $this->info("YouTube Shorts 수집 시작 (최대 {$limit}개, {$days}일 이내)...");

        // 오래된 자동수집 숏츠 정리
        $deleted = Short::whereNull('user_id')
            ->where('created_at', '<', now()->subDays($days * 3))
            ->delete();
        if ($deleted) $this->info("오래된 숏츠 {$deleted}개 삭제");

        $added = 0;
        $skipped = 0;
        $queries = $this->searchQueries;
        shuffle($queries);

        $publishedAfter = now()->subDays($days)->toIso8601String();

        foreach ($queries as $query) {
            if ($added >= $limit) break;

            try {
                $response = Http::timeout(10)->get('https://www.googleapis.com/youtube/v3/search', [
                    'key' => $apiKey,
                    'q' => $query,
                    'part' => 'snippet',
                    'type' => 'video',
                    'videoDuration' => 'short',
                    'order' => 'date',
                    'maxResults' => 20,
                    'relevanceLanguage' => 'ko',
                    'publishedAfter' => $publishedAfter,
                ]);

                if (!$response->ok()) {
                    $this->warn("API 에러 '{$query}': " . $response->status());
                    if ($response->status() === 403) {
                        $this->error('API 할당량 초과! 중단합니다.');
                        break;
                    }
                    continue;
                }

                $items = $response->json()['items'] ?? [];

                foreach ($items as $item) {
                    if ($added >= $limit) break;

                    $videoId = $item['id']['videoId'] ?? null;
                    if (!$videoId) continue;

                    // 중복 체크
                    if (Short::where('youtube_id', $videoId)->exists()) {
                        $skipped++;
                        continue;
                    }

                    $title = $item['snippet']['title'] ?? '무제';
                    $channel = $item['snippet']['channelTitle'] ?? '';

                    // 중국어/일본어 제목 필터
                    if (preg_match('/[\x{4e00}-\x{9fff}\x{3040}-\x{30ff}]/u', $title)) {
                        $skipped++;
                        continue;
                    }

                    Short::create([
                        'user_id' => null,
                        'title' => mb_substr($title, 0, 200),
                        'video_url' => "https://www.youtube.com/shorts/{$videoId}",
                        'youtube_id' => $videoId,
                        'thumbnail_url' => "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg",
                        'duration' => rand(15, 60),
                        'is_active' => true,
                    ]);
                    $added++;
                }
            } catch (\Exception $e) {
                $this->warn("에러 '{$query}': " . $e->getMessage());
            }
        }

        $total = Short::count();
        $this->info("완료! 추가: {$added}, 스킵: {$skipped}, 전체: {$total}");
    }
}
