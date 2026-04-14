<?php

namespace App\Console\Commands;

use App\Models\News;
use App\Models\NewsCategory;
use Illuminate\Console\Command;
use Carbon\Carbon;

/**
 * 오마이뉴스 카테고리별 RSS 에서 최신 기사를 가져온다.
 *
 * - 11개 카테고리 각각 20건 = 최대 220건/회
 * - RSS <description> 에 HTML 본문 + 이미지 포함 (합법적)
 * - 한국어 원문이라 번역 불필요
 * - 2시간마다 자동 실행 (routes/console.php)
 */
class FetchNews extends Command
{
    protected $signature   = 'news:fetch';
    protected $description = '오마이뉴스 카테고리별 RSS 에서 최신 뉴스 가져오기';

    // slug → RSS URL + 한국어 카테고리명
    private array $feeds = [
        'politics'      => ['url' => 'https://rss.ohmynews.com/rss/politics.xml',      'name' => '정치'],
        'economy'       => ['url' => 'https://rss.ohmynews.com/rss/economy.xml',       'name' => '경제'],
        'society'       => ['url' => 'https://rss.ohmynews.com/rss/society.xml',        'name' => '사회'],
        'culture'       => ['url' => 'https://rss.ohmynews.com/rss/culture.xml',        'name' => '문화'],
        'education'     => ['url' => 'https://rss.ohmynews.com/rss/education.xml',      'name' => '교육'],
        'media'         => ['url' => 'https://rss.ohmynews.com/rss/media.xml',          'name' => '미디어'],
        'international' => ['url' => 'https://rss.ohmynews.com/rss/international.xml',  'name' => '민족·국제'],
        'sports'        => ['url' => 'https://rss.ohmynews.com/rss/sports.xml',         'name' => '스포츠'],
        'woman'         => ['url' => 'https://rss.ohmynews.com/rss/woman.xml',          'name' => '여성'],
        'star'          => ['url' => 'https://rss.ohmynews.com/rss/star.xml',           'name' => '스타'],
        'cartoon'       => ['url' => 'https://rss.ohmynews.com/rss/cartoon.xml',        'name' => '만평·만화'],
    ];

    public function handle(): int
    {
        // slug → category_id 캐시
        $categoryIds = NewsCategory::pluck('id', 'slug')->toArray();

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($this->feeds as $slug => $feed) {
            $categoryId = $categoryIds[$slug] ?? null;
            $this->info("=== {$feed['name']} ===");

            $xml = $this->loadRss($feed['url']);
            if (!$xml) {
                $this->warn("  RSS 로드 실패");
                continue;
            }

            $created = 0;
            $skipped = 0;

            foreach ($xml->channel->item as $item) {
                $link  = trim((string) ($item->link ?? ''));
                $title = trim((string) ($item->title ?? ''));
                if (!$link || !$title) continue;
                if (News::where('source_url', $link)->exists()) { $skipped++; continue; }

                // description 에 HTML 본문 + 이미지 포함
                $descHtml = (string) ($item->description ?? '');

                // 이미지: description HTML 에서 첫 img 추출
                $imageUrl = null;
                if (preg_match('/<img[^>]+src="(https?:\/\/[^"]+)"/i', $descHtml, $m)) {
                    $src = $m[1];
                    if (!preg_match('/(logo|icon|pixel|banner)/i', $src)) {
                        $imageUrl = $src;
                    }
                }

                // HTML → 텍스트 (이미지 마커 보존)
                $content = $this->htmlToText($descHtml);
                // "전체 내용보기" 링크 제거
                $content = preg_replace('/전체 내용보기\s*$/u', '', $content);
                $content = trim($content);

                // 발행일
                $pubDate = (string) ($item->pubDate ?? '');
                try {
                    $publishedAt = $pubDate ? Carbon::parse($pubDate) : now();
                } catch (\Exception $e) {
                    $publishedAt = now();
                }

                // 이미지 로컬 다운로드
                $localImage = null;
                if ($imageUrl) {
                    $localImage = $this->downloadImage($imageUrl);
                }

                News::create([
                    'title'        => $title,
                    'content'      => $content,
                    'summary'      => mb_substr($content ?: $title, 0, 300),
                    'source'       => '오마이뉴스',
                    'source_url'   => $link,
                    'image_url'    => $imageUrl,
                    'local_image'  => $localImage,
                    'category_id'  => $categoryId,
                    'published_at' => $publishedAt,
                ]);
                $created++;
            }

            $this->info("  {$feed['name']}: 신규={$created} 중복={$skipped}");
            $totalCreated += $created;
            $totalSkipped += $skipped;
        }

        $this->info("완료: 신규={$totalCreated} 중복={$totalSkipped}");
        return self::SUCCESS;
    }

    private function loadRss(string $url): ?\SimpleXMLElement
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        if (!$body) return null;
        return @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA) ?: null;
    }

    private function htmlToText(string $html): string
    {
        if (!$html) return '';

        $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);

        // img → 마커 보존
        $imgs = [];
        $html = preg_replace_callback('/<img[^>]+src="(https?:\/\/[^"]+)"[^>]*>/i', function ($m) use (&$imgs) {
            $src = $m[1];
            if (preg_match('/(logo|icon|pixel|banner|ad[_-])/i', $src)) return '';
            $idx = count($imgs);
            $imgs[] = $src;
            return "\n\n![뉴스 이미지]({$src})\n\n";
        }, $html);

        // 블록 → 줄바꿈
        $html = preg_replace('/<\/?(p|div|h[1-6]|blockquote|li|tr)[^>]*>/i', "\n\n", $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n[ \t]+/', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        // <a href> "전체 내용보기" 제거
        $text = preg_replace('/전체 내용보기\s*$/u', '', $text);

        if (mb_strlen($text) > 8000) $text = mb_substr($text, 0, 8000);
        return trim($text);
    }

    /**
     * 외부 이미지를 로컬에 다운로드 → /storage/app/public/news/YYYY-MM/hash.jpg
     */
    private function downloadImage(string $url): ?string
    {
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SomeKorean/1.0)',
            ]);
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            if ($httpCode !== 200 || !$imageData || strlen($imageData) < 1000) return null;
            if (!str_contains($contentType ?? '', 'image')) return null;

            // 파일명: MD5 해시 + 확장자
            $ext = 'jpg';
            if (str_contains($contentType, 'png')) $ext = 'png';
            elseif (str_contains($contentType, 'webp')) $ext = 'webp';

            $dir = 'news/' . now()->format('Y-m');
            $filename = md5($url) . '.' . $ext;
            $relPath = $dir . '/' . $filename;

            $absDir = storage_path('app/public/' . $dir);
            if (!is_dir($absDir)) mkdir($absDir, 0775, true);

            file_put_contents(storage_path('app/public/' . $relPath), $imageData);

            return '/storage/' . $relPath;
        } catch (\Exception $e) {
            return null;
        }
    }
}
