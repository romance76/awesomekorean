<?php

namespace App\Console\Commands;

use App\Models\InfoPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * info.awesomekorean.com(별도 도메인, 저장소 접근 불가)에 이미 발행된 글들을
 * 어썸코리안 본 사이트의 info_posts 테이블로 1회성 이전한다.
 *
 * DB/저장소에 직접 접근할 방법이 없어서, 그 사이트 자체가 서버사이드 Blade로
 * 렌더링하는 공개 HTML을 sitemap.xml 목록 기준으로 하나씩 가져와 파싱한다.
 * (이 저장소 쪽엔 리다이렉트를 걸 수단이 없어 301은 별도 진행하지 않음 — 사이트가
 * 생긴 지 얼마 안 돼 누적된 백링크가 많지 않아 손실이 크지 않다고 판단.)
 */
class BackfillInfoPosts extends Command
{
    protected $signature = 'info:backfill {--dry-run : DB에 저장하지 않고 결과만 출력}';
    protected $description = 'info.awesomekorean.com에 발행된 기존 글을 info_posts로 1회성 이전';

    private const SOURCE = 'https://info.awesomekorean.com';

    public function handle(): int
    {
        $sitemap = Http::timeout(20)->get(self::SOURCE . '/sitemap.xml');
        if (!$sitemap->successful()) {
            $this->error('sitemap.xml을 가져오지 못했습니다: HTTP ' . $sitemap->status());
            return self::FAILURE;
        }

        preg_match_all('/<loc>(.*?)<\/loc>/', $sitemap->body(), $matches);
        $urls = array_filter($matches[1] ?? [], fn($u) => str_contains($u, '/articles/'));

        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($urls as $url) {
            try {
                $response = Http::timeout(20)->get($url);
                if (!$response->successful()) {
                    $this->warn("[{$url}] HTTP {$response->status()}");
                    $failed++;
                    continue;
                }

                $parsed = $this->parse($response->body(), $url);
                if (!$parsed) {
                    $this->warn("[{$url}] 파싱 실패 (필수 요소 없음)");
                    $failed++;
                    continue;
                }

                if (InfoPost::where('slug', $parsed['slug'])->exists()) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("DRY-RUN: [{$parsed['category']}] {$parsed['title']}");
                } else {
                    InfoPost::create($parsed);
                }
                $created++;
            } catch (\Throwable $e) {
                $this->warn("[{$url}] 예외: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("완료: 이전={$created}, 중복스킵={$skipped}, 실패={$failed}");
        return self::SUCCESS;
    }

    private function parse(string $html, string $url): ?array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $h1 = $xpath->query('//article//h1')->item(0);
        $bodyNode = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " prose ")]')->item(0);
        $timeNode = $xpath->query('//time[@datetime]')->item(0);
        if (!$h1 || !$bodyNode) return null;

        $title = trim($h1->textContent);
        $body = $this->innerHtml($bodyNode, $dom);
        if (!$title || !$body) return null;

        $titleNode = $xpath->query('//title')->item(0);
        $descNode = $xpath->query("//meta[@name='description']")->item(0);
        $metaTitle = ($titleNode ? trim($titleNode->textContent) : null) ?: $title;
        $metaDescription = $descNode ? trim($descNode->getAttribute('content')) : null;
        $category = $this->extractCategory($xpath);
        $publishedAt = $timeNode ? ($timeNode->getAttribute('datetime') ?: null) : null;
        $coverImage = $this->firstImageSrc($bodyNode) ?: $this->ogImage($xpath);

        $slugSource = rawurldecode(trim(parse_url($url, PHP_URL_PATH), '/'));
        $slug = preg_replace('#^articles/#', '', $slugSource);

        return [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $metaDescription,
            'body' => $body,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'cover_image_url' => $coverImage,
            'category' => in_array($category, InfoPost::CATEGORIES, true) ? $category : '생활정보',
            'is_published' => true,
            'published_at' => $publishedAt ? \Carbon\Carbon::parse($publishedAt) : now(),
        ];
    }

    private function innerHtml(\DOMElement $node, \DOMDocument $dom): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }
        return trim($html);
    }

    private function ogImage(\DOMXPath $xpath): ?string
    {
        $node = $xpath->query("//meta[@property='og:image']")->item(0);
        return $node ? trim($node->getAttribute('content')) : null;
    }

    private function firstImageSrc(\DOMElement $node): ?string
    {
        $img = $node->getElementsByTagName('img')->item(0);
        return $img ? $img->getAttribute('src') : null;
    }

    // "금융" 같은 카테고리명을 category 링크(href=".../category/%EA%B8%88%EC%9C%B5")에서 추출
    private function extractCategory(\DOMXPath $xpath): string
    {
        foreach ($xpath->query('//a[contains(@href,"/category/")]') as $a) {
            $path = parse_url($a->getAttribute('href'), PHP_URL_PATH);
            if (!$path) continue;
            $name = rawurldecode(basename($path));
            if ($name) return $name;
        }
        return '생활정보';
    }
}
