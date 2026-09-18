<?php

namespace App\Console\Commands;

use App\Models\ExternalHeadline;
use Illuminate\Console\Command;
use Carbon\Carbon;

/**
 * 여러 언론사 RSS에서 제목/썸네일/링크만 가져와 저장한다 (본문 재호스팅 안 함).
 *
 * 대부분의 언론사 RSS는 오마이뉴스처럼 전체 본문 재배포 권한을 주지 않으므로,
 * 네이버 뉴스스탠드처럼 헤드라인 카드만 보여주고 클릭 시 원문 사이트로 이동시키는
 * 방식으로 라이선스 문제를 피한다.
 */
class FetchExternalHeadlines extends Command
{
    protected $signature   = 'headlines:fetch';
    protected $description = '여러 언론사 RSS에서 헤드라인(제목+썸네일+링크)만 가져오기';

    // slug => [name, rss url, RSS에 이미지 태그가 없으면 og:image 를 추가로 가져올지]
    private array $feeds = [
        'asiae'    => ['아시아경제', 'https://www.asiae.co.kr/rss/all.htm', false],
        'etnews'   => ['전자신문',   'https://rss.etnews.com/Section901.xml', true],
        'kormedi'  => ['코메디닷컴', 'https://kormedi.com/feed', false],
        'yna'      => ['연합뉴스',   'https://www.yna.co.kr/rss/news.xml', false],
        'seoul'    => ['서울신문',   'https://www.seoul.co.kr/xml/rss/rss_politics.xml', false],
        'newsis'   => ['뉴시스',     'https://newsis.com/RSS/sokbo.xml', true],
        'segye'    => ['세계일보',   'http://www.segye.com/Articles/RSSList/segye_recent.xml', false],
        'khan'     => ['경향신문',   'http://www.khan.co.kr/rss/rssdata/total_news.xml', true],
    ];

    // 헤드라인 위젯이 과거 기사로 부풀지 않도록 이 건수를 넘으면 오래된 것부터 정리
    private const MAX_KEEP = 200;

    public function handle(): int
    {
        $totalCreated = 0;

        foreach ($this->feeds as $slug => [$name, $url, $needsOgImage]) {
            $xml = $this->loadRss($url);
            if (!$xml) {
                $this->warn("[{$name}] RSS 로드 실패");
                continue;
            }

            $items = $xml->channel->item ?? null;
            if (!$items) {
                $this->warn("[{$name}] 아이템 없음");
                continue;
            }

            $created = 0;
            $count = 0;
            foreach ($items as $item) {
                if (++$count > 20) break; // 소스당 최신 20건만 확인

                $link  = trim((string) ($item->link ?? ''));
                $title = trim((string) ($item->title ?? ''));
                if (!$link || !$title) continue;
                if (ExternalHeadline::where('source_url', $link)->exists()) continue;

                $desc = (string) ($item->description ?? '');
                $image = $this->extractImage($item, $desc);
                if (!$image && $needsOgImage) {
                    $image = $this->fetchOgImage($link);
                }
                $summary = $this->extractSummary($desc);
                // 요약이 없으면(설명이 비어있는 피드) 클릭해도 볼 내용이 없으므로 수집하지 않음
                if (!$summary) continue;

                $pubDate = (string) ($item->pubDate ?? '');
                try {
                    $publishedAt = $pubDate ? Carbon::parse($pubDate) : now();
                } catch (\Exception $e) {
                    $publishedAt = now();
                }

                ExternalHeadline::create([
                    'source'       => $name,
                    'source_slug'  => $slug,
                    'title'        => $title,
                    'summary'      => $summary,
                    'source_url'   => $link,
                    'image_url'    => $image,
                    'published_at' => $publishedAt,
                ]);
                $created++;
            }

            $this->info("[{$name}] 신규 {$created}건");
            $totalCreated += $created;
        }

        // 오래된 헤드라인 정리 (링크아웃 위젯이라 과거 기사는 의미 없음)
        $ids = ExternalHeadline::orderByDesc('published_at')->pluck('id');
        if ($ids->count() > self::MAX_KEEP) {
            ExternalHeadline::whereIn('id', $ids->slice(self::MAX_KEEP)->values())->delete();
        }

        $this->info("완료: 총 신규={$totalCreated}");
        return self::SUCCESS;
    }

    // RSS description에서 이미지/태그를 걷어낸 짧은 텍스트만 추출 (본문
    // 전체가 아니라 RSS가 원래 제공하는 짧은 요약 수준만 사용)
    private function extractSummary(string $desc): ?string
    {
        $text = strip_tags($desc);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);
        if (!$text) return null;
        return mb_substr($text, 0, 200);
    }

    private function extractImage(\SimpleXMLElement $item, string $desc): ?string
    {
        $ns = $item->getNamespaces(true);
        if (isset($ns['media'])) {
            $media = $item->children($ns['media']);
            if (isset($media->thumbnail) && $media->thumbnail->attributes()->url) {
                return (string) $media->thumbnail->attributes()->url;
            }
            if (isset($media->content) && $media->content->attributes()->url) {
                return (string) $media->content->attributes()->url;
            }
        }
        if (isset($item->enclosure) && $item->enclosure->attributes()->url) {
            return (string) $item->enclosure->attributes()->url;
        }
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $desc, $m)) {
            $src = $m[1];
            if (!preg_match('/(logo|icon|pixel|banner|ad[_-])/i', $src)) {
                return str_starts_with($src, '//') ? 'https:' . $src : $src;
            }
        }
        return null;
    }

    private function fetchOgImage(string $articleUrl): ?string
    {
        try {
            $ch = curl_init($articleUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; AwesomeKorean/1.0)',
                CURLOPT_RANGE => '0-60000', // 헤드 부분만 받아 og:image 찾기 (본문 전체 다운로드 불필요)
            ]);
            $html = curl_exec($ch);
            curl_close($ch);
            if (!$html) return null;
            if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
                $src = $m[1];
                // 사이트 공용 로고/기본 이미지는 실제 기사 사진이 아니므로 제외
                if (preg_match('/(logo|default|noimage|no_image|pixel|banner)/i', $src)) return null;
                return $src;
            }
        } catch (\Exception $e) {
            // 무시하고 이미지 없이 진행
        }
        return null;
    }

    private function loadRss(string $url): ?\SimpleXMLElement
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        if (!$body) return null;
        return @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA) ?: null;
    }
}
