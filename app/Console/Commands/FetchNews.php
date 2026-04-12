<?php

namespace App\Console\Commands;

use App\Models\News;
use App\Models\NewsCategory;
use App\Models\ApiKey;
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
    protected $signature   = 'news:fetch';
    protected $description = 'TIME Magazine RSS 에서 최신 뉴스 가져오기 (Google Translate EN→KO)';

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

    // 영어 키워드 → 카테고리 slug 매핑 (아리랑/TIME 공용)
    // 순서 중요: 먼저 매칭되면 해당 카테고리로 확정
    private array $enCategoryRules = [
        'entertainment' => ['BTS', 'BLACKPINK', 'K-pop', 'K-POP', 'Kpop', 'idol', 'concert', 'world tour', 'Billboard', 'album', 'singer', 'band', 'movie', 'film', 'drama', 'Netflix', 'Disney', 'actor', 'actress', 'K-drama', 'celebrity', 'Oscar', 'Cannes', 'box office', 'streaming', 'TV show', 'entertainment', 'Hollywood', 'music', 'award', 'MONSTA X', 'Jennie', 'Hallyu'],
        'sports' => ['soccer', 'football', 'baseball', 'basketball', 'golf', 'tennis', 'Olympic', 'athlete', 'FIFA', 'MLB', 'NBA', 'NFL', 'Premier League', 'Champions League', 'World Cup', 'KOSPI', 'tournament', 'match score', 'season record', 'league standing', 'coach', 'goalkeeper', 'striker'],
        'economy' => ['economy', 'economic', 'GDP', 'stock', 'market', 'trade', 'export', 'import', 'inflation', 'interest rate', 'unemployment', 'investment', 'Samsung', 'Hyundai', 'business', 'company', 'corporate', 'revenue', 'profit', 'dollar', 'won', 'currency', 'tariff', 'semiconductor', 'chip'],
        'tech' => ['AI ', 'artificial intelligence', 'robot', 'tech', 'technology', 'digital', 'cyber', 'hack', 'software', 'startup', 'drone', 'EV ', 'electric vehicle', 'battery', 'semiconductor', 'chip', 'space', 'NASA', 'satellite', 'SpaceX', '5G', '6G', 'quantum'],
        'society' => ['crime', 'police', 'court', 'trial', 'sentence', 'accident', 'crash', 'earthquake', 'typhoon', 'flood', 'fire', 'death', 'victim', 'protest', 'rally', 'human rights', 'discrimination', 'education', 'school', 'university', 'student'],
        'politics' => ['president', 'minister', 'parliament', 'congress', 'election', 'vote', 'party', 'legislation', 'bill', 'policy', 'diplomatic', 'summit', 'sanction', 'impeach', 'Democrat', 'Republican', 'liberal', 'conservative', 'coalition'],
        'lifestyle' => ['health', 'hospital', 'medical', 'disease', 'vaccine', 'diet', 'food', 'recipe', 'travel', 'tourism', 'hotel', 'weather', 'forecast', 'temperature', 'rain', 'snow', 'fashion', 'beauty', 'wellness'],
    ];

    public function handle(): int
    {
        // 카테고리 slug → id 캐시
        $this->categoryIdCache = NewsCategory::pluck('id', 'slug')->toArray();

        $totalCreated = 0;
        $totalSkipped = 0;

        // 아리랑 뉴스 (공공데이터 API — 합법적)
        $this->info('=== 아리랑 뉴스 API ===');
        [$c, $s] = $this->fetchArirang();
        $totalCreated += $c; $totalSkipped += $s;

        // TIME Magazine RSS
        $this->info('=== TIME Magazine RSS ===');
        [$c, $s] = $this->fetchTime();
        $totalCreated += $c; $totalSkipped += $s;

        $this->info("완료: 신규={$totalCreated} 중복={$totalSkipped}");
        return self::SUCCESS;
    }

    // ─────────────────────── 아리랑 (공공데이터 API) ───────────────────────

    private function fetchArirang(): array
    {
        $apiKey = ApiKey::keyFor('arirang_news');
        if (!$apiKey) {
            $this->warn('  아리랑 API 키가 등록되지 않았습니다 (관리자 → API 키 관리)');
            return [0, 0];
        }

        $created = 0; $skipped = 0;
        $page = 1;
        $perPage = 50;

        // 최대 3페이지 (150건) 까지 가져오기
        while ($page <= 3) {
            $url = 'https://apis.data.go.kr/B551024/openArirangNewsApi/news?' . http_build_query([
                'serviceKey' => $apiKey,
                'numOfRows' => $perPage,
                'pageNo' => $page,
                'type' => 'json',
            ]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_USERAGENT => 'Mozilla/5.0',
            ]);
            $body = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$body) {
                $this->warn("  아리랑 API 응답 실패: HTTP={$httpCode}");
                break;
            }

            $json = json_decode($body, true);
            // data.go.kr 표준 응답: response.body.items 또는 직접 배열
            $items = $json['response']['body']['items'] ?? $json['data'] ?? $json['items'] ?? $json ?? [];
            if (is_array($items) && isset($items[0])) {
                // 배열 직접
            } elseif (isset($items['item'])) {
                $items = $items['item'];
            } else {
                $this->warn("  아리랑 응답 구조 파싱 실패 (page={$page})");
                break;
            }

            if (empty($items)) break;

            foreach ($items as $item) {
                $title = trim($item['title'] ?? $item['TITLE'] ?? '');
                $newsUrl = trim($item['news_url'] ?? $item['NEWS_URL'] ?? $item['url'] ?? '');
                $content = trim($item['content'] ?? $item['CONTENT'] ?? '');
                $imgUrl = trim($item['img_url'] ?? $item['IMG_URL'] ?? $item['thumbnail'] ?? '');
                $broadcastDate = $item['broadcast_date'] ?? $item['BROADCAST_DATE'] ?? $item['date'] ?? null;

                if (!$title) continue;
                $sourceUrl = $newsUrl ?: ('arirang-' . md5($title));

                if (News::where('source_url', $sourceUrl)->exists()) { $skipped++; continue; }

                // 번역 (아리랑은 영어 방송)
                $this->info("  번역 중: " . mb_substr($title, 0, 40) . '...');
                $titleKo = $this->translateText($title) ?: $title;

                // 본문이 있으면 번역
                $contentKo = '';
                $contentEn = '';
                if ($content && mb_strlen($content) > 50) {
                    $contentEn = $content;
                    $contentKo = $this->translateLongText($content) ?: $content;
                }

                // 카테고리: 영어 제목+본문 키워드로 분류
                $classifyText = $title . ' ' . $content;
                $categorySlug = $this->classifyByKeywords($classifyText);
                $categoryId = $this->categoryIdCache[$categorySlug] ?? ($this->categoryIdCache['world'] ?? null);

                $publishedAt = $broadcastDate ? Carbon::parse($broadcastDate) : now();
                $source = ($titleKo === $title) ? '아리랑 (번역 실패)' : '아리랑';

                News::create([
                    'title'        => $titleKo,
                    'title_en'     => $title,
                    'content'      => $contentKo,
                    'content_en'   => $contentEn ?: null,
                    'summary'      => mb_substr($contentKo ?: $titleKo, 0, 300),
                    'source'       => $source,
                    'source_url'   => $sourceUrl,
                    'image_url'    => $imgUrl ?: null,
                    'category_id'  => $categoryId,
                    'published_at' => $publishedAt,
                ]);
                $created++;
            }

            $page++;
        }

        $this->info("  아리랑: 신규={$created} 중복={$skipped}");
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

            // 카테고리: RSS category 태그 우선, 없으면 키워드 분류
            $categorySlug = null;
            foreach ($item->category as $cat) {
                $catName = trim((string) $cat);
                if (isset($this->timeCategoryMap[$catName])) {
                    $categorySlug = $this->timeCategoryMap[$catName];
                    break;
                }
            }
            if (!$categorySlug) {
                $categorySlug = $this->classifyByKeywords($title . ' ' . $contentEn);
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

    /**
     * 영어 제목+본문에서 키워드 기반 카테고리 분류
     */
    private function classifyByKeywords(string $text): string
    {
        foreach ($this->enCategoryRules as $slug => $keywords) {
            foreach ($keywords as $kw) {
                if (stripos($text, $kw) !== false) {
                    return $slug;
                }
            }
        }
        return 'world'; // 기본값: 국제
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
        $html = preg_replace('/<figcaption[^>]*>.*?<\/figcaption>/si', '', $html);

        // img 태그 → 마커로 보존
        $imgs = [];
        $html = preg_replace_callback('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', function ($m) use (&$imgs) {
            $src = $m[1];
            if (!str_starts_with($src, 'http')) return '';
            if (preg_match('/(logo|icon|pixel|banner|ad[_-]|ads?\/|tracking)/i', $src)) return '';
            $idx = count($imgs);
            $imgs[] = $src;
            return "\n\n![뉴스 이미지]({$src})\n\n";
        }, $html);

        // 블록 요소 → 줄바꿈 (strip_tags 전에)
        $html = preg_replace('/<\/?(p|div|h[1-6]|blockquote|li|tr|section|article)[^>]*>/i', "\n\n", $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);

        // HTML→plain
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // 정리
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n[ \t]+/', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        // TIME "Read full article" / "Comments" 링크 제거
        $text = preg_replace('/\s*Read full article\s*/i', '', $text);
        $text = preg_replace('/\s*Comments\s*$/i', '', $text);

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
     * 각 단락을 개별 번역해서 줄바꿈 유지.
     */
    private function translateLongText(string $text, string $to = 'ko', string $from = 'en'): ?string
    {
        if (mb_strlen($text) < 10) return $text;

        // 이미지 마커 추출
        $imgMarkers = [];
        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function ($m) use (&$imgMarkers) {
            $idx = count($imgMarkers);
            $imgMarkers[] = $m[0];
            return "{{IMG{$idx}}}";
        }, $text);

        // 단락별로 개별 번역 (줄바꿈 유지 위해)
        $paragraphs = preg_split('/\n{2,}/', $text);
        $translated = [];

        foreach ($paragraphs as $p) {
            $p = trim($p);
            if ($p === '') continue;

            // 이미지 마커만 있는 줄은 번역하지 않음
            if (preg_match('/^\{\{IMG\d+\}\}$/', $p)) {
                $translated[] = $p;
                continue;
            }

            // 짧은 단락은 개별 번역
            if (mb_strlen($p) <= 4500) {
                $result = $this->translateText($p, $to, $from);
                if ($result === null) {
                    usleep(3000000);
                    $result = $this->translateText($p, $to, $from);
                }
                $translated[] = $result ?? $p;
            } else {
                // 긴 단락: 문장 단위로 분할
                $sentences = preg_split('/(?<=[.!?])\s+/', $p);
                $chunks = [];
                $current = '';
                foreach ($sentences as $s) {
                    if (mb_strlen($current) + mb_strlen($s) + 1 > 4500 && $current !== '') {
                        $chunks[] = $current;
                        $current = $s;
                    } else {
                        $current .= ($current ? ' ' : '') . $s;
                    }
                }
                if ($current !== '') $chunks[] = $current;

                $chunkResults = [];
                foreach ($chunks as $chunk) {
                    $result = $this->translateText($chunk, $to, $from);
                    $chunkResults[] = $result ?? $chunk;
                    usleep(300000);
                }
                $translated[] = implode(' ', $chunkResults);
            }

            usleep(200000); // 200ms 대기
        }

        $output = implode("\n\n", $translated);

        // 이미지 마커 복원
        foreach ($imgMarkers as $i => $marker) {
            $output = str_replace("{{IMG{$i}}}", $marker, $output);
        }

        return $output;
    }
}
