<?php

namespace App\Console\Commands;

use App\Models\StockNews;
use App\Support\EventImporter;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * 증권 페이지 "증권가 뉴스" — 증권·시황 언론사 RSS에서 제목·짧은 요약·원문 링크만 가져온다(본문 재게시 안 함).
 * 영문 기사(CNBC)는 제목만 한국어로 번역해 저장하고, 클릭하면 원문으로 이동한다.
 * 보관 기간은 2개월 — 15분마다 도는 이 명령이 매번 오래된 기사를 지운다.
 */
class FetchStockNews extends Command
{
    protected $signature   = 'stocknews:fetch';
    protected $description = '증권가 뉴스(증권·시황 RSS)를 stock_news 에 가져오기';

    // 이름 => [RSS 주소, 언어, 증시 관련 단어가 제목에 있는 기사만 담을지]
    private array $feeds = [
        '한국경제'     => ['https://www.hankyung.com/feed/finance', 'ko', false],
        '연합인포맥스' => ['https://news.einfomax.co.kr/rss/allArticle.xml', 'ko', false],
        '연합뉴스'     => ['https://www.yna.co.kr/rss/economy.xml', 'ko', true],
        'CNBC'         => ['https://search.cnbc.com/rs/search/combinedcms/view.xml?partnerId=wrss01&id=15839069', 'en', false],
    ];

    private const KEYWORDS = ['증시', '주가', '주식', '코스피', '코스닥', '나스닥', '다우', 'S&P', '뉴욕', '월가', '연준', 'Fed', '금리', '환율', '달러', '국채', '비트코인', '실적', '반도체', '엔비디아', '테슬라', '애플', '상장', 'IPO', 'ETF', '증권', '투자'];

    private const KEEP_MONTHS = 2;      // 이 기간이 지난 기사는 삭제
    private const PER_FEED = 25;        // 소스당 최신 N건만 확인
    private const MAX_TRANSLATE = 12;   // 한 번에 번역할 영문 제목 수 (번역 요청 절약)

    public function handle(): int
    {
        $total = 0;
        $translated = 0;

        foreach ($this->feeds as $name => [$url, $lang, $filter]) {
            $xml = $this->loadRss($url);
            if (!$xml || !isset($xml->channel->item)) {
                $this->warn("[{$name}] RSS 로드 실패");
                continue;
            }

            $created = 0;
            $seen = 0;
            foreach ($xml->channel->item as $item) {
                if (++$seen > self::PER_FEED) break;

                $link  = trim((string) ($item->link ?? ''));
                $title = $this->clean((string) ($item->title ?? ''));
                if (!$link || !$title) continue;
                $hash = sha1($link);
                if (StockNews::where('url_hash', $hash)->exists()) continue;

                $summary = $this->clean((string) ($item->description ?? ''));
                if ($filter && !$this->isStockRelated($title . ' ' . $summary)) continue;

                if ($lang === 'en') {
                    if ($translated >= self::MAX_TRANSLATE) continue;
                    $ko = trim(EventImporter::translate($title));
                    if ($ko === '' || $ko === $title) continue;      // 번역 실패 시 영문 제목을 그대로 노출하지 않고 다음 실행에 다시 시도
                    $title = $ko;
                    $summary = '';                                    // 영문 요약은 번역하지 않음
                    $translated++;
                }

                try {
                    $pub = ($d = (string) ($item->pubDate ?? '')) ? Carbon::parse($d) : now();
                } catch (\Throwable $e) {
                    $pub = now();
                }

                StockNews::create([
                    'source' => $name, 'title' => mb_substr($title, 0, 500),
                    'summary' => $summary !== '' ? mb_substr($summary, 0, 200) : null,
                    'url' => mb_substr($link, 0, 1000), 'url_hash' => $hash,
                    'image_url' => $this->extractImage($item), 'lang' => $lang, 'published_at' => $pub,
                ]);
                $created++;
            }
            $this->info("[{$name}] 신규 {$created}건");
            $total += $created;
        }

        // 2개월이 지난 기사는 삭제 (매 실행마다 확인)
        $deleted = StockNews::where('published_at', '<', now()->subMonths(self::KEEP_MONTHS))->delete();

        $this->info("완료: 총 신규={$total}, 2개월 지난 기사 삭제={$deleted}");
        return self::SUCCESS;
    }

    private function isStockRelated(string $text): bool
    {
        foreach (self::KEYWORDS as $w) {
            if (mb_stripos($text, $w) !== false) return true;
        }
        return false;
    }

    private function clean(string $s): string
    {
        $s = html_entity_decode(html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    private function extractImage(\SimpleXMLElement $item): ?string
    {
        $ns = $item->getNamespaces(true);
        if (isset($ns['media'])) {
            $m = $item->children($ns['media']);
            foreach (['thumbnail', 'content'] as $k) {
                if (isset($m->$k) && $m->$k->attributes()->url) return (string) $m->$k->attributes()->url;
            }
        }
        if (isset($item->enclosure) && $item->enclosure->attributes()->url) return (string) $item->enclosure->attributes()->url;
        return null;
    }

    private function loadRss(string $url): ?\SimpleXMLElement
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        if (!$body) return null;
        return @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA) ?: null;
    }
}
