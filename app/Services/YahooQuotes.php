<?php

namespace App\Services;

use App\Models\MarketQuote;
use Illuminate\Support\Facades\Http;

/**
 * Yahoo Finance 비공식 chart API 에서 시세를 받아 market_quotes 에 저장한다 (키 불필요).
 * 전일 대비는 "직전 거래일 종가"를 기준으로 계산한다(이전엔 한 달 전 종가 기준이라 등락률이 크게 틀렸다).
 */
class YahooQuotes
{
    /** 화면 기본 목록: 미국 주요 지수/지표 */
    public const INDICES = [
        '^GSPC'   => 'S&P 500',
        '^DJI'    => 'Dow 30',
        '^IXIC'   => 'Nasdaq',
        '^RUT'    => 'Russell 2000',
        '^TNX'    => '10-Yr Bond',
        '^VIX'    => 'VIX',
        'GC=F'    => 'Gold',
        'BTC-USD' => 'Bitcoin USD',
    ];

    /** 메인 위젯 옆에 보이는 기본 종목 */
    public const WATCHLIST = [
        'AAPL' => 'Apple', 'NVDA' => 'NVIDIA', 'TSLA' => 'Tesla', 'MSFT' => 'Microsoft', 'AMZN' => 'Amazon',
    ];

    private const HEADERS = ['User-Agent' => 'Mozilla/5.0'];
    private const URL = 'https://query1.finance.yahoo.com/v8/finance/chart/';

    /** @return array|null 저장된 시세 필드 (실패하면 null) */
    public function fetch(string $symbol): ?array
    {
        try {
            $res = Http::withHeaders(self::HEADERS)->timeout(10)->get(self::URL . urlencode($symbol), ['range' => '1mo', 'interval' => '1d']);
            return $res->successful() ? $this->parse($res->json()) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** chart API 응답 → 저장할 필드 */
    public function parse(?array $json): ?array
    {
        $result = $json['chart']['result'][0] ?? null;
        if (!$result) return null;
        $meta = $result['meta'] ?? [];
        $price = $meta['regularMarketPrice'] ?? null;
        if ($price === null) return null;

        $closes = array_values(array_filter($result['indicators']['quote'][0]['close'] ?? [], fn($v) => $v !== null));
        // 마지막 막대가 최신 거래일(진행 중이거나 마감) → 그 앞 막대가 직전 거래일 종가
        $prev = count($closes) >= 2 ? $closes[count($closes) - 2] : ($meta['chartPreviousClose'] ?? null);
        $change = $prev ? $price - $prev : null;

        return [
            'name'       => $meta['shortName'] ?? $meta['longName'] ?? ($meta['symbol'] ?? ''),
            'price'      => $price,
            'change'     => $change !== null ? round($change, 2) : null,
            'change_pct' => $prev ? round(($change / $prev) * 100, 3) : null,
            'volume'     => $meta['regularMarketVolume'] ?? null,
            'sparkline'  => array_slice($closes, -20),
            'quoted_at'  => isset($meta['regularMarketTime']) ? \Carbon\Carbon::createFromTimestamp($meta['regularMarketTime']) : now(),
        ];
    }

    /** 기본 지수/종목 심볼 → [이름, 분류, 정렬] */
    public static function defaults(): array
    {
        $out = []; $i = 0;
        foreach (self::INDICES as $sym => $name) $out[$sym] = [$name, 'index', $i++];
        $i = 0;
        foreach (self::WATCHLIST as $sym => $name) $out[$sym] = [$name, 'watchlist', $i++];
        return $out;
    }

    /**
     * 여러 심볼을 한꺼번에(동시에) 받아 저장한다. 서버의 스케줄러(cron)가 믿을 수 없이 가끔 도는 환경이라,
     * 방문자가 시세를 요청했을 때 오래됐으면 이 함수로 바로 새로 받는다.
     * @param string[] $symbols
     */
    public function refreshMany(array $symbols): int
    {
        if (!$symbols) return 0;
        $defaults = self::defaults();
        try {
            $responses = Http::pool(function ($pool) use ($symbols) {
                foreach ($symbols as $sym) {
                    $pool->as($sym)->withHeaders(self::HEADERS)->timeout(8)->get(self::URL . urlencode($sym), ['range' => '1mo', 'interval' => '1d']);
                }
            });
        } catch (\Throwable $e) {
            return 0;
        }
        $saved = 0;
        foreach ($symbols as $sym) {
            $r = $responses[$sym] ?? null;
            if (!$r instanceof \Illuminate\Http\Client\Response || !$r->successful()) continue;
            $d = $this->parse($r->json());
            if (!$d) continue;
            [$name, $cat, $order] = $defaults[$sym] ?? [null, 'user', 0];
            if ($name) $d['name'] = $name;
            $existing = MarketQuote::where('symbol', $sym)->first();
            if ($existing && $existing->category !== 'user') { $cat = $existing->category; }
            MarketQuote::updateOrCreate(['symbol' => $sym], $d + ['category' => $cat, 'sort_order' => $order]);
            $saved++;
        }
        return $saved;
    }

    /**
     * 기본 지수/종목 시세가 없거나 $maxAgeSec 보다 오래됐으면 새로 받는다.
     * 없을 때는 지금 바로, 오래됐을 뿐이면 응답을 보낸 뒤에 받는다(방문자를 기다리게 하지 않음).
     */
    public function ensureFresh(int $maxAgeSec = 300): void
    {
        $defaults = array_keys(self::defaults());
        $rows = MarketQuote::whereIn('symbol', $defaults)->get(['symbol', 'updated_at']);
        $missing = count($defaults) - $rows->count();
        $oldest = $rows->min('updated_at');
        $stale = $oldest && $oldest->lt(now()->subSeconds($maxAgeSec));
        if ($missing <= 0 && !$stale) return;
        if (!\Illuminate\Support\Facades\Cache::add('market-refresh-lock', 1, 60)) return;   // 동시에 여러 방문자가 와도 한 번만

        if ($missing > 0) { $this->refreshMany($defaults); return; }
        dispatch(fn() => $this->refreshMany($defaults))->afterResponse();
    }

    /** 가져와서 저장. $name 을 주면 그 이름을 쓰고, 아니면 Yahoo 이름 */
    public function fetchAndSave(string $symbol, ?string $name, string $category, int $order = 0): ?MarketQuote
    {
        $d = $this->fetch($symbol);
        if (!$d) return null;
        if ($name) $d['name'] = $name;
        $existing = MarketQuote::where('symbol', $symbol)->first();
        // 기본 목록(index/watchlist)에 이미 있는 심볼의 분류는 사용자 추가로 바뀌지 않게 한다
        if ($existing && $existing->category !== 'user') $category = $existing->category;
        return MarketQuote::updateOrCreate(['symbol' => $symbol], $d + ['category' => $category, 'sort_order' => $order]);
    }
}
