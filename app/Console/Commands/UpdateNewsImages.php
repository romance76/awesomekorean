<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\News;

class UpdateNewsImages extends Command
{
    protected $signature   = 'news:update-images {--limit=50 : 한 번에 처리할 최대 기사 수}';
    protected $description = '이미지가 없는 기존 뉴스 기사에 og:image를 가져와 업데이트합니다';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $articles = News::whereNull('image_url')
            ->orWhere('image_url', '')
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();

        $this->info("이미지 없는 기사 {$articles->count()}건 처리 시작...");

        $updated = 0;
        $failed  = 0;

        foreach ($articles as $article) {
            $imageUrl = $this->extractArticleImage($article->url);
            if ($imageUrl) {
                $article->update(['image_url' => $imageUrl]);
                $updated++;
                $this->line("  ✓ [{$article->id}] " . mb_substr($article->title, 0, 40));
            } else {
                $failed++;
            }
            // Rate limit: don't hammer servers
            usleep(200000); // 0.2s
        }

        $this->info("완료: 업데이트 {$updated}건, 이미지 없음 {$failed}건");
        return self::SUCCESS;
    }

    private function extractArticleImage(string $url): ?string
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout'    => 8,
                    'user_agent' => 'Mozilla/5.0 (compatible; AwesomeKorean/1.0)',
                    'follow_location' => true,
                ],
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $html = @file_get_contents($url, false, $context, 0, 12288);
            if (!$html) return null;

            // og:image (두 순서 모두 시도)
            if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/', $html, $m)) {
                return $m[1];
            }
            if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/', $html, $m)) {
                return $m[1];
            }
            // twitter:image
            if (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/', $html, $m)) {
                return $m[1];
            }
            if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']twitter:image["\']/', $html, $m)) {
                return $m[1];
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
