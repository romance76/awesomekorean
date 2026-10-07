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

    /** @return array|null 저장된 시세 필드 (실패하면 null) */
    public function fetch(string $symbol): ?array
    {
        try {
            $res = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])->timeout(10)
                ->get('https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($symbol), ['range' => '1mo', 'interval' => '1d']);
            if (!$res->successful()) return null;
            $result = $res->json('chart.result.0');
            if (!$result) return null;
            $meta = $result['meta'] ?? [];
            $price = $meta['regularMarketPrice'] ?? null;
            if ($price === null) return null;

            $closes = array_values(array_filter($result['indicators']['quote'][0]['close'] ?? [], fn($v) => $v !== null));
            // 마지막 막대가 최신 거래일(진행 중이거나 마감) → 그 앞 막대가 직전 거래일 종가
            $prev = count($closes) >= 2 ? $closes[count($closes) - 2] : ($meta['chartPreviousClose'] ?? null);
            $change = $prev ? $price - $prev : null;

            return [
                'name'       => $meta['shortName'] ?? $meta['longName'] ?? $symbol,
                'price'      => $price,
                'change'     => $change !== null ? round($change, 2) : null,
                'change_pct' => $prev ? round(($change / $prev) * 100, 3) : null,
                'volume'     => $meta['regularMarketVolume'] ?? null,
                'sparkline'  => array_slice($closes, -20),
                'quoted_at'  => isset($meta['regularMarketTime']) ? \Carbon\Carbon::createFromTimestamp($meta['regularMarketTime']) : now(),
            ];
        } catch (\Throwable $e) {
            return null;
        }
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
