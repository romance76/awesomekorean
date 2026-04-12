<?php

namespace App\Console\Commands;

use App\Models\News;
use Illuminate\Console\Command;

/**
 * 이미 DB 에 있는 뉴스 기사의 본문을 다시 스크래핑한다.
 * FetchNews 의 파싱 로직이 개선되면 이 명령으로 백필.
 */
class RescrapeNews extends Command
{
    protected $signature = 'news:rescrape {--source=} {--min-len=300} {--mod=} {--total=}';
    protected $description = 'Re-fetch article content for news whose body is too short';

    public function handle(): int
    {
        $source = $this->option('source');
        $minLen = (int) $this->option('min-len');
        $mod = $this->option('mod');
        $total = $this->option('total');
        $useShard = ($mod !== null && $total !== null);

        $q = News::whereNotNull('source_url')
            ->where('source_url', '!=', '')
            ->whereRaw("CHAR_LENGTH(COALESCE(content, '')) < ?", [$minLen]);
        if ($source) $q->where('source', $source);
        if ($useShard) $q->whereRaw('id % ? = ?', [(int)$total, (int)$mod]);

        $count = $q->count();
        $this->info("Rescraping $count articles" . ($useShard ? " [worker $mod/$total]" : ''));

        $ok = 0; $fail = 0;
        $q->orderBy('id')->chunkById(50, function ($rows) use (&$ok, &$fail) {
            foreach ($rows as $n) {
                $body = $this->fetchBody($n->source_url);
                if ($body && mb_strlen($body) >= 200) {
                    $n->content = $body;
                    $n->save();
                    $ok++;
                } else {
                    $fail++;
                }
            }
        });

        $this->info("DONE ok=$ok fail=$fail");
        return 0;
    }

    /**
     * FetchNews 와 동일한 파싱 로직 (중복이지만 컴맨드가 독립적이어야 해서 복사).
     */
    private function fetchBody(string $url): ?string
    {
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ]);
            $html = curl_exec($ch);
            curl_close($ch);
            if (!$html || strlen($html) < 500) return null;

            // 스크립트/스타일/네비 제거
            $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
            $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);
            $html = preg_replace('/<nav[^>]*>.*?<\/nav>/si', '', $html);
            $html = preg_replace('/<header[^>]*>.*?<\/header>/si', '', $html);
            $html = preg_replace('/<footer[^>]*>.*?<\/footer>/si', '', $html);

            $text = null;
            if (str_contains($url, 'koreadaily.com') && preg_match('/<!--#dev body-->(.*?)<!--\s*태그 영역/s', $html, $m)) {
                $text = $m[1];
            } elseif (str_contains($url, 'sbs.co.kr') && preg_match('/<div[^>]*class=["\'][^"\']*w_article_cont[^"\']*["\'][^>]*>(.*?)<div[^>]*class=["\'][^"\']*(?:w_article_side|article_relation_area|w_article_byline)/si', $html, $m)) {
                $text = $m[1];
            } else {
                return null;
            }

            // img 태그 유지
            $imgs = [];
            $text = preg_replace_callback('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', function ($mm) use (&$imgs) {
                $src = $mm[1];
                if (!str_starts_with($src, 'http')) return '';
                if (preg_match('/(logo|icon|pixel|banner|ad[_-]|ads?\/)/i', $src)) return '';
                $idx = count($imgs);
                $imgs[] = $src;
                return "\n[IMG:$idx]\n";
            }, $text);

            $text = strip_tags($text);
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            foreach ($imgs as $i => $src) {
                $text = str_replace("[IMG:$i]", "\n![뉴스 이미지]($src)\n", $text);
            }

            $text = preg_replace('/[ \t]+/', ' ', $text);
            $text = preg_replace('/\n\s*\n/', "\n\n", $text);
            $text = trim($text);

            if (mb_strlen($text) > 8000) $text = mb_substr($text, 0, 8000);
            return mb_strlen($text) >= 100 ? $text : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
