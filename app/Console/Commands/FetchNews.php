<?php

namespace App\Console\Commands;

use App\Models\News;
use Illuminate\Console\Command;
use Carbon\Carbon;

class FetchNews extends Command
{
    protected $signature   = 'news:fetch';
    protected $description = '한인 뉴스 RSS 피드를 가져와 DB에 저장합니다';

    // 각 피드: [url, type] — type 은 'rss' 또는 'sitemap_news' (Google News sitemap)
    private array $feeds = [
        // 미주 한인 언론
        '미주중앙일보'  => ['url' => 'https://www.koreadaily.com/sitemap/latest-articles',  'type' => 'sitemap_news'],
        // 한국 주요 언론
        'SBS뉴스'     => ['url' => 'https://news.sbs.co.kr/news/SectionRssFeed.do?sectionId=01&plink=RSSREADER', 'type' => 'rss'],
    ];

    private array $categoryKeywords = [
        // 이민/비자 — 가장 먼저 검사 (한인들의 핵심 관심사)
        '이민/비자'  => [
            '비자', 'visa', 'Visa', 'USCIS', '이민', '영주권', 'green card', 'Green Card',
            'H-1B', 'H1B', 'F-1', 'OPT', 'DACA', 'work permit', '취업비자', '학생비자',
            '시민권', 'citizenship', 'naturalization', '귀화', '추방', 'deportation',
            'immigration', 'Immigration', '이민국', '이민법', '비자 인터뷰', 'I-485', 'I-130',
            'asylum', '망명', 'refugee', '난민', 'border', '국경',
        ],
        '미국생활'   => [
            '미국', 'USA', 'America', '한인', '코리아타운', 'Koreatown', '한국인',
            '미주', '교민', '세금신고', 'tax return', 'IRS', '소셜시큐리티', 'Social Security',
            '운전면허', 'DMV', '건강보험', 'health insurance', 'Medicare', 'Medicaid',
            '렌트', 'rent', '부동산', '학군', '공립학교', 'public school', '대입',
        ],
        '정치/사회'  => ['대통령', '국회', '선거', '법원', '경찰', '정치', '정부', '외교', '북한', '트럼프', '바이든', '행정명령'],
        '경제'       => ['경제', '주식', '달러', '환율', '투자', '금리', '코스피', '나스닥', '은행', '세금', 'GDP', '관세', '무역'],
        '생활'       => ['건강', '교육', '학교', '대학', '요리', '맛집', '병원', '의료', '보험', '날씨', '생활'],
        '문화'       => ['드라마', '영화', '음악', 'K-pop', '한류', '공연', 'BTS', '넷플릭스', '문화', '예술'],
        '스포츠'     => ['야구', '축구', '농구', '올림픽', '골프', 'MLB', 'NBA', 'NFL', '손흥민', '오타니', '김하성'],
    ];

    public function handle(): int
    {
        $created = 0;
        $skipped = 0;

        foreach ($this->feeds as $source => $feedDef) {
            $url = $feedDef['url'];
            $type = $feedDef['type'];
            $this->info("피드 가져오는 중: {$source} ({$type})");

            try {
                $context = stream_context_create([
                    'http' => [
                        'timeout'    => 15,
                        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    ],
                    'ssl' => [
                        'verify_peer'      => false,
                        'verify_peer_name' => false,
                    ],
                ]);

                $xml = @file_get_contents($url, false, $context);
                if ($xml === false) {
                    $this->warn("  피드를 가져올 수 없습니다: {$source}");
                    continue;
                }

                $feed = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
                if (!$feed) {
                    $this->warn("  XML 파싱 실패: {$source}");
                    continue;
                }

                // sitemap_news 는 <urlset><url>...</url></urlset>, RSS 는 <channel><item>...</item></channel>
                if ($type === 'sitemap_news') {
                    $items = $feed->url ?? [];
                } else {
                    $items = $feed->channel->item ?? $feed->entry ?? [];
                }

                foreach ($items as $item) {
                    if ($type === 'sitemap_news') {
                        // Google News sitemap: <url><loc>...</loc><news:news><news:title>...</news:title><news:publication_date>...</news:publication_date></news:news></url>
                        $link = (string) ($item->loc ?? '');
                        $namespaces = $item->getNameSpaces(true);
                        $newsNode = isset($namespaces['news']) ? $item->children($namespaces['news']) : null;
                        $newsItem = $newsNode?->news;
                        $title = $newsItem ? (string) $newsItem->title : '';
                        $pubDate = $newsItem ? (string) $newsItem->publication_date : (string) ($item->lastmod ?? '');
                        $description = '';
                    } else {
                        $link  = (string) ($item->link ?? '');
                        $title = (string) ($item->title ?? '');
                        $pubDate = (string) ($item->pubDate ?? $item->published ?? '');
                        $description = strip_tags((string) ($item->description ?? ''));
                    }

                    if (!$link || !$title) {
                        continue;
                    }

                    if (News::where('source_url', $link)->exists()) {
                        $skipped++;
                        continue;
                    }

                    $summary = mb_substr($description, 0, 300);

                    // 전체 기사 본문 가져오기
                    $content = $this->fetchArticleContent($link);
                    if (!$content) {
                        $content = $description; // RSS description / sitemap 은 빈 문자열일 수 있음
                    }

                    // 이미지 URL 추출
                    $imageUrl = null;
                    if ($type !== 'sitemap_news') {
                        $namespaces = $item->getNameSpaces(true);
                        if (isset($namespaces['media'])) {
                            $media = $item->children($namespaces['media']);
                            if (isset($media->content)) {
                                $imageUrl = (string) $media->content->attributes()->url;
                            } elseif (isset($media->thumbnail)) {
                                $imageUrl = (string) $media->thumbnail->attributes()->url;
                            }
                        }
                        if (!$imageUrl && isset($item->enclosure)) {
                            $encType = (string) $item->enclosure->attributes()->type;
                            if (str_starts_with($encType, 'image/')) {
                                $imageUrl = (string) $item->enclosure->attributes()->url;
                            }
                        }
                    }
                    // og:image fallback 으로 기사 HTML 에서 추출
                    if (!$imageUrl) {
                        $imageUrl = $this->extractArticleImage($link);
                    }

                    // 발행일 파싱
                    $publishedAt = null;
                    if ($pubDate) {
                        try {
                            $publishedAt = Carbon::parse($pubDate);
                        } catch (\Exception $e) {
                            $publishedAt = null;
                        }
                    }

                    $category = $this->detectCategory($title . ' ' . $description);

                    // 카테고리 매핑
                    $categoryId = \App\Models\NewsCategory::where('name', 'like', "%{$category}%")->first()?->id
                        ?? \App\Models\NewsCategory::first()?->id;

                    // 제목+소스 중복 방지
                    if (News::where('title', $title)->where('source', $source)->exists()) continue;

                    News::create([
                        'title'        => $title,
                        'summary'      => $summary ?: mb_substr($content, 0, 300),
                        'content'      => $content,
                        'source_url'   => $link,
                        'source'       => $source,
                        'category_id'  => $categoryId,
                        'image_url'    => $imageUrl,
                        'published_at' => $publishedAt ?? now(),
                    ]);

                    $created++;
                }

                $this->info("  완료: {$source}");
            } catch (\Exception $e) {
                $this->error("  오류 발생 ({$source}): " . $e->getMessage());
            }
        }

        $this->info("뉴스 가져오기 완료: 신규 {$created}건, 중복 {$skipped}건");

        return self::SUCCESS;
    }

    /**
     * 기사 URL에서 전체 본문 텍스트를 추출합니다.
     */
    private function fetchArticleContent(string $url): ?string
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout'    => 10,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'header'     => "Accept: text/html\r\nAccept-Language: ko-KR,ko;q=0.9\r\n",
                ],
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $html = @file_get_contents($url, false, $context);
            if ($html === false || strlen($html) < 100) {
                return null;
            }

            // script, style, nav, header, footer 태그 제거
            $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
            $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);
            $html = preg_replace('/<nav[^>]*>.*?<\/nav>/si', '', $html);
            $html = preg_replace('/<header[^>]*>.*?<\/header>/si', '', $html);
            $html = preg_replace('/<footer[^>]*>.*?<\/footer>/si', '', $html);
            $html = preg_replace('/<aside[^>]*>.*?<\/aside>/si', '', $html);

            $text = null;

            // 조선일보 전용: article_body
            if (preg_match('/<section[^>]*class=["\'][^"\']*article_body[^"\']*["\'][^>]*>(.*?)<\/section>/si', $html, $matches)) {
                $text = $matches[1];
            }
            // 동아일보: article_txt
            elseif (preg_match('/<div[^>]*class=["\'][^"\']*article_txt[^"\']*["\'][^>]*>(.*?)<\/div>/si', $html, $matches)) {
                $text = $matches[1];
            }
            // SBS: text_area
            elseif (preg_match('/<div[^>]*class=["\'][^"\']*text_area[^"\']*["\'][^>]*>(.*?)<\/div>/si', $html, $matches)) {
                $text = $matches[1];
            }
            // <article> 태그에서 추출 시도
            elseif (preg_match('/<article[^>]*>(.*?)<\/article>/si', $html, $matches)) {
                $text = $matches[1];
            }
            // article-body 클래스 div에서 추출 시도
            elseif (preg_match('/<div[^>]*class=["\'][^"\']*article[-_]?(body|content|text)[^"\']*["\'][^>]*>(.*?)<\/div>/si', $html, $matches)) {
                $text = $matches[2];
            }
            // story-body 또는 news-body에서 추출 시도
            elseif (preg_match('/<div[^>]*class=["\'][^"\']*(?:story|news|post)[-_]?(body|content|text)[^"\']*["\'][^>]*>(.*?)<\/div>/si', $html, $matches)) {
                $text = $matches[2];
            }
            // id="content" 또는 id="article"에서 추출 시도
            elseif (preg_match('/<div[^>]*id=["\'](?:content|article|articleBody)["\'][^>]*>(.*?)<\/div>/si', $html, $matches)) {
                $text = $matches[1];
            }
            // 본문 body 전체 폴백
            else {
                if (preg_match('/<body[^>]*>(.*?)<\/body>/si', $html, $matches)) {
                    $text = $matches[1];
                }
            }

            if (!$text) {
                return null;
            }

            // img 태그는 보존하고 나머지 HTML 제거
            // 1. img 태그 추출하여 마커로 교체
            $imgTags = [];
            $text = preg_replace_callback('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', function($m) use (&$imgTags) {
                $src = $m[1];
                // 작은 이미지(아이콘/로고) 필터링
                if (strpos($src, 'logo') !== false || strpos($src, 'icon') !== false || strpos($src, 'pixel') !== false) return '';
                if (strlen($src) < 10) return '';
                // 절대 URL만
                if (!str_starts_with($src, 'http')) return '';
                $idx = count($imgTags);
                $imgTags[] = $src;
                return "\n[IMG:{$idx}]\n";
            }, $text);

            // 2. 나머지 HTML 태그 제거
            $text = strip_tags($text);
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

            // 3. img 마커를 실제 마크다운 이미지로 교체
            foreach ($imgTags as $idx => $src) {
                $text = str_replace("[IMG:{$idx}]", "\n![뉴스 이미지]({$src})\n", $text);
            }

            // 연속 공백/줄바꿈 정리
            $text = preg_replace('/[ \t]+/', ' ', $text);
            $text = preg_replace('/\n\s*\n/', "\n\n", $text);
            $text = trim($text);

            // 최대 8000자로 제한 (이미지 포함이므로 늘림)
            if (mb_strlen($text) > 8000) {
                $text = mb_substr($text, 0, 8000);
            }

            // 너무 짧으면 무시
            if (mb_strlen($text) < 50) {
                return null;
            }

            return $text;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function extractArticleImage(string $url): ?string
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 8,
                    'user_agent' => 'Mozilla/5.0 (SomeKorean Bot)',
                ],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);

            // Fetch only first 5KB to get meta tags without downloading full page
            $html = @file_get_contents($url, false, $context, 0, 8192);
            if (!$html) return null;

            // Try og:image
            if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/', $html, $m)) {
                return $m[1];
            }
            if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/', $html, $m)) {
                return $m[1];
            }

            // Try twitter:image
            if (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/', $html, $m)) {
                return $m[1];
            }

            // Try first img with src that looks like an article image (not logo/icon)
            if (preg_match_all('/<img[^>]+src=["\']([^"\']+\.(jpg|jpeg|png|webp))["\'][^>]*>/i', $html, $matches)) {
                foreach ($matches[1] as $imgSrc) {
                    // Skip tiny images (icons, logos)
                    if (strpos($imgSrc, 'logo') !== false) continue;
                    if (strpos($imgSrc, 'icon') !== false) continue;
                    if (strpos($imgSrc, 'pixel') !== false) continue;
                    if (strlen($imgSrc) < 10) continue;
                    // Return first valid image
                    if (str_starts_with($imgSrc, 'http')) {
                        return $imgSrc;
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function detectCategory(string $text): string
    {
        foreach ($this->categoryKeywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_strpos($text, $keyword) !== false) {
                    return $category;
                }
            }
        }

        return '기타';
    }
}
