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

    // 이름 => [RSS 주소, 언어, 미국 주식 관련 기사만 담을지 (한국 증시 기사는 제외)]
    private array $feeds = [
        '한국경제'     => ['https://www.hankyung.com/feed/finance', 'ko', true],
        '연합인포맥스' => ['https://news.einfomax.co.kr/rss/allArticle.xml', 'ko', true],
        '연합뉴스'     => ['https://www.yna.co.kr/rss/economy.xml', 'ko', true],
        'CNBC'         => ['https://search.cnbc.com/rs/search/combinedcms/view.xml?partnerId=wrss01&id=15839069', 'en', false],
    ];

    // 미국 주식 관련 표시 (제목에 하나라도 있으면 미국 기사로 본다)
    public const US_WORDS = ['뉴욕증시', '뉴욕 증시', '뉴욕', '월가', '월스트리트', '美', '미국', '나스닥', '다우', 'S&P', '러셀', '연준', '파월', 'FOMC', '엔비디아', '테슬라', '애플', '마이크로소프트', '알파벳', '구글', '아마존', '메타', '브로드컴', '팔란티어', 'AMD', '인텔', '넷플릭스', '오라클', '스페이스X', '서학개미', '해외주식', 'NVDA', 'TSLA', 'AAPL', 'MSFT'];
    // 한국 증시·시장 기사 표시 (제목에 이게 있고 미국 표시가 없으면 제외)
    public const KR_WORDS = ['코스피', '코스닥', '국내 증시', '국내증시', '한국거래소', '금통위', '국고', '달러-원', '원·달러', '원/달러', '삼성전자', 'SK하이닉스', '개미'];
    // 제목에 미국 표시가 없어도 요약에 이 단어가 있으면 미국 증시 기사로 본다
    public const US_STRONG = ['뉴욕증시', '뉴욕 증시', '나스닥', 'S&P', '다우', '월가'];

    private const KEEP_MONTHS = 2;      // 이 기간이 지난 기사는 삭제
    private const PER_FEED = 60;        // 소스당 최신 N건만 확인
    private const OG_PER_RUN = 20;      // 한 번에 기사 페이지에서 대표 이미지를 찾아볼 최대 건수 (실행 시간 보호)
    private const MAX_TRANSLATE = 12;   // 한 번에 번역할 영문 제목 수 (번역 요청 절약)

    public function handle(): int
    {
        $total = 0;
        $translated = 0;
        $ogLeft = self::OG_PER_RUN;

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
                if ($filter && !self::isUsStock($title, $summary)) continue;

                if ($lang === 'en') {
                    $summary = '';                                    // 영문 요약은 번역하지 않음
                    $ko = $translated < self::MAX_TRANSLATE ? trim(EventImporter::translate($title)) : '';
                    if ($ko !== '' && $ko !== $title) { $title = $ko; $translated++; }
                    else $lang = 'en_raw';                            // 번역이 안 되면 영문 제목으로 먼저 올리고, 다음 실행에서 다시 번역 시도
                }

                try {
                    $pub = ($d = (string) ($item->pubDate ?? '')) ? Carbon::parse($d) : now();
                } catch (\Throwable $e) {
                    $pub = now();
                }

                // RSS에 그림이 없으면 기사 페이지의 대표 이미지(og:image)를 찾는다. 못 찾으면 '' 로 남겨 다시 시도하지 않음
                $image = $this->extractImage($item);
                if (!$image && $ogLeft > 0) { $ogLeft--; $image = $this->fetchOgImage($link) ?? ''; }

                StockNews::create([
                    'source' => $name, 'title' => mb_substr($title, 0, 500),
                    'summary' => $summary !== '' ? mb_substr($summary, 0, 200) : null,
                    'url' => mb_substr($link, 0, 1000), 'url_hash' => $hash,
                    'image_url' => $image ?: ($image === '' ? '' : null), 'lang' => $lang, 'published_at' => $pub,
                ]);
                $created++;
            }
            $this->info("[{$name}] 신규 {$created}건");
            $total += $created;
        }

        // 예전에 그림 없이 들어온 기사에도 대표 이미지를 채워 넣는다 (남은 횟수만큼, 최신 기사부터)
        if ($ogLeft > 0) {
            foreach (StockNews::whereNull('image_url')->orderByDesc('published_at')->limit($ogLeft)->get() as $row) {
                $row->update(['image_url' => $this->fetchOgImage($row->url) ?? '']);
            }
        }

        // 번역이 안 돼 영문 제목으로 올라간 기사를 다시 번역해 본다 (번역 API가 복구되면 자동으로 한국어로 바뀜)
        foreach (StockNews::where('lang', 'en_raw')->orderByDesc('published_at')->limit(self::MAX_TRANSLATE)->get() as $row) {
            $ko = trim(EventImporter::translate($row->title));
            if ($ko !== '' && $ko !== $row->title) $row->update(['title' => mb_substr($ko, 0, 500), 'lang' => 'en']);
            else break;                                               // 아직 막혀 있으면 이번 실행에서는 더 시도하지 않음
        }

        // 2개월이 지난 기사는 삭제 (매 실행마다 확인)
        $deleted = StockNews::where('published_at', '<', now()->subMonths(self::KEEP_MONTHS))->delete();

        $this->info("완료: 총 신규={$total}, 2개월 지난 기사 삭제={$deleted}");
        return self::SUCCESS;
    }

    /** 미국 주식 관련 기사인지 — 한국 증시 기사는 제외 */
    public static function isUsStock(string $title, string $summary = ''): bool
    {
        $has = function (string $text, array $words): bool {
            foreach ($words as $w) { if (mb_stripos($text, $w) !== false) return true; }
            return false;
        };
        if ($has($title, self::US_WORDS)) return true;
        return !$has($title, self::KR_WORDS) && $has($summary, self::US_STRONG);
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

    /** 기사 페이지 앞부분만 받아 og:image 를 찾는다 (로고·기본 이미지는 제외) */
    private function fetchOgImage(string $url): ?string
    {
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 8, CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; AwesomeKorean/1.0)', CURLOPT_RANGE => '0-60000',
            ]);
            $html = curl_exec($ch);
            curl_close($ch);
            if (!$html) return null;
            if (preg_match('/<meta[^>]+property=["']og:image["'][^>]+content=["']([^"']+)["']/i', $html, $m)
                || preg_match('/<meta[^>]+content=["']([^"']+)["'][^>]+property=["']og:image["']/i', $html, $m)) {
                $src = html_entity_decode($m[1]);
                if (preg_match('/(logo|default|noimage|no_image|pixel|banner)/i', $src)) return null;
                return $src;
            }
        } catch (Throwable $e) {
            // 이미지 없이 진행
        }
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
