<?php

namespace App\Console\Commands;

use App\Models\News;
use Illuminate\Console\Command;
use Carbon\Carbon;

class FetchNews extends Command
{
    protected $signature   = 'news:fetch';
    protected $description = '한인 뉴스 RSS 피드를 가져와 DB에 저장합니다';

    private array $feeds = [
        '연합뉴스' => 'https://www.yna.co.kr/rss/all.xml',
        'KBS'     => 'https://world.kbs.co.kr/rss/rss_news.htm?lang=k',
        '한겨레'   => 'https://www.hani.co.kr/rss/',
    ];

    private array $categoryKeywords = [
        '정치/사회' => ['대통령', '국회', '선거', '법원', '경찰', '이민', '비자', '정치', '정부', '외교', '북한', '트럼프', '바이든'],
        '경제'     => ['경제', '주식', '부동산', '달러', '환율', '투자', '금리', '코스피', '나스닥', '은행', '세금', 'GDP'],
        '생활'     => ['건강', '교육', '학교', '대학', '요리', '맛집', '병원', '의료', '보험', '운전', '날씨', '생활'],
        '문화'     => ['드라마', '영화', '음악', 'K-pop', '한류', '공연', 'BTS', '넷플릭스', '문화', '예술', '전시'],
        '스포츠'   => ['야구', '축구', '농구', '올림픽', '골프', 'MLB', 'NBA', 'NFL', '손흥민', '오타니', '김하성'],
    ];

    public function handle(): int
    {
        $created = 0;
        $skipped = 0;

        foreach ($this->feeds as $source => $url) {
            $this->info("피드 가져오는 중: {$source}");

            try {
                $context = stream_context_create([
                    'http' => [
                        'timeout'    => 15,
                        'user_agent' => 'Mozilla/5.0 (SomeKorean News Bot)',
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

                $items = $feed->channel->item ?? $feed->entry ?? [];

                foreach ($items as $item) {
                    $link  = (string) ($item->link ?? '');
                    $title = (string) ($item->title ?? '');

                    if (!$link || !$title) {
                        continue;
                    }

                    if (News::where('url', $link)->exists()) {
                        $skipped++;
                        continue;
                    }

                    $description = strip_tags((string) ($item->description ?? ''));
                    $summary     = mb_substr($description, 0, 300);

                    // 전체 기사 본문 가져오기
                    $content = $this->fetchArticleContent($link);
                    if (!$content) {
                        $content = $description; // RSS description을 폴백으로 사용
                    }

                    // 이미지 URL 추출
                    $imageUrl = null;
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
                        $type = (string) $item->enclosure->attributes()->type;
                        if (str_starts_with($type, 'image/')) {
                            $imageUrl = (string) $item->enclosure->attributes()->url;
                        }
                    }

                    // 발행일
                    $pubDate = $item->pubDate ?? $item->published ?? null;
                    $publishedAt = null;
                    if ($pubDate) {
                        try {
                            $publishedAt = Carbon::parse((string) $pubDate);
                        } catch (\Exception $e) {
                            $publishedAt = null;
                        }
                    }

                    $category = $this->detectCategory($title . ' ' . $description);

                    News::create([
                        'title'        => $title,
                        'summary'      => $summary ?: mb_substr($content, 0, 300),
                        'content'      => $content,
                        'url'          => $link,
                        'source'       => $source,
                        'category'     => $category,
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

            // <article> 태그에서 추출 시도
            if (preg_match('/<article[^>]*>(.*?)<\/article>/si', $html, $matches)) {
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

            // HTML 태그 제거 및 정리
            $text = strip_tags($text);
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            // 연속 공백/줄바꿈 정리
            $text = preg_replace('/[ \t]+/', ' ', $text);
            $text = preg_replace('/\n\s*\n/', "\n\n", $text);
            $text = trim($text);

            // 최대 5000자로 제한
            if (mb_strlen($text) > 5000) {
                $text = mb_substr($text, 0, 5000);
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
