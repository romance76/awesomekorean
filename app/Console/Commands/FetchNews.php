<?php

namespace App\Console\Commands;

use App\Models\News;
use App\Models\NewsCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

/**
 * SBS뉴스 + TIME 매거진 RSS 에서 최신 기사를 가져온다.
 *
 * - RSS 에서 제공하는 콘텐츠만 사용 (합법적)
 * - TIME 은 Google Translate 로 한→영 번역, 원문도 함께 저장
 * - 2시간마다 자동 실행 (routes/console.php)
 */
class FetchNews extends Command
{
    protected $signature   = 'news:fetch {--source=all : sbs|time|all}';
    protected $description = 'SBS + TIME RSS 에서 최신 뉴스 가져오기';

    // SBS <category> 태그 → slug 매핑
    private array $sbsCategoryMap = [
        '정치'   => 'politics',
        '경제'   => 'economy',
        '사회'   => 'society',
        '생활문화' => 'lifestyle',
        '생활/문화' => 'lifestyle',
        '국제'   => 'world',
        '스포츠'  => 'sports',
        'IT/과학' => 'tech',
        'IT과학'  => 'tech',
        '연예'   => 'entertainment',
    ];

    // TIME <category> → slug 매핑
    private array $timeCategoryMap = [
        'Politics' => 'politics',
        'Business' => 'economy',
        'World'    => 'world',
        'U.S.'     => 'society',
        'Health'   => 'lifestyle',
        'Entertainment' => 'entertainment',
        'Sports'   => 'sports',
        'Science'  => 'tech',
        'Tech'     => 'tech',
        'Ideas'    => 'society',
        'Climate'  => 'society',
    ];

    private array $categoryIdCache = [];

    public function handle(): int
    {
        $source = $this->option('source');

        // 카테고리 slug → id 캐시
        $this->categoryIdCache = NewsCategory::pluck('id', 'slug')->toArray();

        $created = 0;
        $skipped = 0;

        if ($source === 'all' || $source === 'sbs') {
            $this->info('=== SBS 뉴스 RSS ===');
            [$c, $s] = $this->fetchSbs();
            $created += $c; $skipped += $s;
        }

        if ($source === 'all' || $source === 'time') {
            $this->info('=== TIME Magazine RSS ===');
            [$c, $s] = $this->fetchTime();
            $created += $c; $skipped += $s;
        }

        $this->info("완료: 신규={$created} 중복={$skipped}");
        return self::SUCCESS;
    }

    // ─────────────────────── SBS ───────────────────────

    private function fetchSbs(): array
    {
        $xml = $this->loadRss('https://news.sbs.co.kr/news/SectionRssFeed.do?sectionId=01&plink=RSSREADER');
        if (!$xml) { $this->warn('SBS RSS 로드 실패'); return [0, 0]; }

        $ns = $xml->channel->item[0]?->getNameSpaces(true) ?? [];
        $created = 0; $skipped = 0;

        foreach ($xml->channel->item as $item) {
            $link  = trim((string) ($item->link ?? ''));
            $title = trim((string) ($item->title ?? ''));
            if (!$link || !$title) continue;
            if (News::where('source_url', $link)->exists()) { $skipped++; continue; }

            // content:encoded 에서 본문
            $contentHtml = '';
            $itemNs = $item->getNameSpaces(true);
            if (isset($itemNs['content'])) {
                $ce = $item->children($itemNs['content']);
                $contentHtml = (string) ($ce->encoded ?? '');
            }
            $content = $this->htmlToText($contentHtml);

            // description 폴백
            $desc = strip_tags((string) ($item->description ?? ''));
            if (mb_strlen($content) < 100 && mb_strlen($desc) > mb_strlen($content)) {
                $content = $desc;
            }

            // 이미지: media:content → enclosure → content 내 첫 img
            $imageUrl = $this->extractMediaImage($item, $itemNs)
                     ?: $this->extractEnclosureImage($item)
                     ?: $this->extractFirstImgFromHtml($contentHtml);

            // 카테고리
            $categorySlug = null;
            foreach ($item->category as $cat) {
                $catName = trim((string) $cat);
                if (isset($this->sbsCategoryMap[$catName])) {
                    $categorySlug = $this->sbsCategoryMap[$catName];
                    break;
                }
            }
            $categoryId = $categorySlug ? ($this->categoryIdCache[$categorySlug] ?? null) : null;

            // 발행일
            $pubDate = (string) ($item->pubDate ?? '');
            $publishedAt = $pubDate ? Carbon::parse($pubDate) : now();

            News::create([
                'title'        => $title,
                'content'      => $content,
                'summary'      => mb_substr($content ?: $title, 0, 300),
                'source'       => 'SBS뉴스',
                'source_url'   => $link,
                'image_url'    => $imageUrl ? html_entity_decode($imageUrl) : null,
                'category_id'  => $categoryId,
                'published_at' => $publishedAt,
            ]);
            $created++;
        }

        $this->info("  SBS: 신규={$created} 중복={$skipped}");
        return [$created, $skipped];
    }

    // ─────────────────────── TIME ───────────────────────

    private function fetchTime(): array
    {
        $xml = $this->loadRss('https://time.com/feed/');
        if (!$xml) { $this->warn('TIME RSS 로드 실패'); return [0, 0]; }

        $created = 0; $skipped = 0;

        foreach ($xml->channel->item as $item) {
            $link  = trim((string) ($item->link ?? ''));
            $title = trim((string) ($item->title ?? ''));
            if (!$link || !$title) continue;
            if (News::where('source_url', $link)->exists()) { $skipped++; continue; }

            // content:encoded 에서 영어 본문
            $contentHtml = '';
            $itemNs = $item->getNameSpaces(true);
            if (isset($itemNs['content'])) {
                $ce = $item->children($itemNs['content']);
                $contentHtml = (string) ($ce->encoded ?? '');
            }
            $contentEn = $this->htmlToText($contentHtml);

            // 이미지
            $imageUrl = $this->extractMediaImage($item, $itemNs)
                     ?: $this->extractEnclosureImage($item)
                     ?: $this->extractFirstImgFromHtml($contentHtml);

            // 카테고리
            $categorySlug = 'world'; // 기본값
            foreach ($item->category as $cat) {
                $catName = trim((string) $cat);
                if (isset($this->timeCategoryMap[$catName])) {
                    $categorySlug = $this->timeCategoryMap[$catName];
                    break;
                }
            }
            $categoryId = $this->categoryIdCache[$categorySlug] ?? null;

            // 발행일
            $pubDate = (string) ($item->pubDate ?? '');
            $publishedAt = $pubDate ? Carbon::parse($pubDate) : now();

            // Google Translate: 영어→한글
            $this->info("  번역 중: " . mb_substr($title, 0, 40) . '...');
            $titleKo = $this->translateText($title) ?: $title;
            $contentKo = $this->translateLongText($contentEn) ?: $contentEn;
            $source = ($titleKo === $title && $contentKo === $contentEn) ? 'TIME (번역 실패)' : 'TIME';

            News::create([
                'title'        => $titleKo,
                'title_en'     => $title,
                'content'      => $contentKo,
                'content_en'   => $contentEn,
                'summary'      => mb_substr($contentKo ?: $titleKo, 0, 300),
                'source'       => $source,
                'source_url'   => $link,
                'image_url'    => $imageUrl ? html_entity_decode($imageUrl) : null,
                'category_id'  => $categoryId,
                'published_at' => $publishedAt,
            ]);
            $created++;
        }

        $this->info("  TIME: 신규={$created} 중복={$skipped}");
        return [$created, $skipped];
    }

    // ─────────────────────── RSS 로드 ───────────────────────

    private function loadRss(string $url): ?\SimpleXMLElement
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        if (!$body) return null;
        return @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA) ?: null;
    }

    // ─────────────────────── HTML→Text ───────────────────────

    private function htmlToText(string $html): string
    {
        if (!$html) return '';

        // 스크립트/광고 제거
        $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);

        // img 태그 → 마커로 보존
        $imgs = [];
        $html = preg_replace_callback('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', function ($m) use (&$imgs) {
            $src = $m[1];
            if (!str_starts_with($src, 'http')) return '';
            if (preg_match('/(logo|icon|pixel|banner|ad[_-]|ads?\/|tracking)/i', $src)) return '';
            $idx = count($imgs);
            $imgs[] = $src;
            return "\n[IMG:{$idx}]\n";
        }, $html);

        // HTML→plain
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // 이미지 마커 복원
        foreach ($imgs as $i => $src) {
            $text = str_replace("[IMG:{$i}]", "\n![뉴스 이미지]({$src})\n", $text);
        }

        // 정리
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n/', "\n\n", $text);
        $text = trim($text);

        // 불필요한 SBS 꼬리 텍스트 제거
        $text = preg_replace('/Copyright\s*Ⓒ\s*SBS.*$/s', '', $text);
        $text = preg_replace('/좋아요\s+\d.*$/s', '', $text);
        $text = preg_replace('/기자\s*페이지 바로가기.*$/s', '', $text);

        // TIME "Read full article" 링크 제거
        $text = preg_replace('/Read full article/i', '', $text);
        $text = preg_replace('/Comments/i', '', $text);

        if (mb_strlen($text) > 8000) $text = mb_substr($text, 0, 8000);
        return trim($text);
    }

    // ─────────────────────── 이미지 추출 ───────────────────────

    private function extractMediaImage(\SimpleXMLElement $item, array $ns): ?string
    {
        if (!isset($ns['media'])) return null;
        $media = $item->children($ns['media']);
        if (isset($media->content)) {
            $url = (string) $media->content->attributes()['url'];
            if ($url) return $url;
        }
        if (isset($media->thumbnail)) {
            $url = (string) $media->thumbnail->attributes()['url'];
            if ($url) return $url;
        }
        return null;
    }

    private function extractEnclosureImage(\SimpleXMLElement $item): ?string
    {
        if (!isset($item->enclosure)) return null;
        $type = (string) $item->enclosure->attributes()['type'];
        if (str_starts_with($type, 'image/')) {
            return (string) $item->enclosure->attributes()['url'];
        }
        return null;
    }

    private function extractFirstImgFromHtml(string $html): ?string
    {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
            $src = $m[1];
            if (str_starts_with($src, 'http') && !preg_match('/(logo|icon|pixel|banner)/i', $src)) {
                return $src;
            }
        }
        return null;
    }

    // ─────────────────────── Google 번역 ───────────────────────

    private function translateText(string $text, string $to = 'ko', string $from = 'en'): ?string
    {
        $text = trim($text);
        if ($text === '') return '';
        try {
            $res = Http::timeout(15)->get('https://translate.googleapis.com/translate_a/single', [
                'client' => 'gtx',
                'sl' => $from,
                'tl' => $to,
                'dt' => 't',
                'q' => $text,
            ]);
            if (!$res->ok()) return null;
            $arr = $res->json();
            if (!is_array($arr) || empty($arr[0])) return null;
            $out = '';
            foreach ($arr[0] as $seg) {
                if (isset($seg[0])) $out .= $seg[0];
            }
            return trim($out);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 긴 텍스트를 단락 단위로 분할해서 번역.
     * 이미지 마커 ![alt](url) 는 번역 전 추출, 후 복원.
     */
    private function translateLongText(string $text, string $to = 'ko', string $from = 'en'): ?string
    {
        if (mb_strlen($text) < 10) return $text;

        // 이미지 마커 추출
        $imgMarkers = [];
        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function ($m) use (&$imgMarkers) {
            $idx = count($imgMarkers);
            $imgMarkers[] = $m[0]; // 전체 마커 보존
            return "{{IMG{$idx}}}";
        }, $text);

        // 단락 분할 + 4500자 청킹
        $paragraphs = preg_split('/\n{2,}/', $text);
        $chunks = [];
        $current = '';
        foreach ($paragraphs as $p) {
            if (mb_strlen($current) + mb_strlen($p) + 2 > 4500 && $current !== '') {
                $chunks[] = $current;
                $current = $p;
            } else {
                $current .= ($current ? "\n\n" : '') . $p;
            }
        }
        if ($current !== '') $chunks[] = $current;

        // 각 청크 번역
        $translated = [];
        foreach ($chunks as $i => $chunk) {
            $result = $this->translateText($chunk, $to, $from);
            if ($result === null) {
                // 재시도
                usleep(3000000); // 3초 대기
                $result = $this->translateText($chunk, $to, $from);
            }
            $translated[] = $result ?? $chunk; // 실패 시 원문 유지
            usleep(300000); // 300ms 대기
        }

        $output = implode("\n\n", $translated);

        // 이미지 마커 복원
        foreach ($imgMarkers as $i => $marker) {
            $output = str_replace("{{IMG{$i}}}", $marker, $output);
        }

        return $output;
    }
}
