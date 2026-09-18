<?php

namespace App\Console\Commands;

use App\Models\MarketQuote;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * 메인 화면 "인기 주식" 위젯 + 증권 페이지용 시세를 Yahoo Finance 비공식
 * chart API에서 가져와 캐싱한다 (키 불필요, 무료). 방문자 브라우저가 매번
 * 직접 호출하면 비공식 API라 CORS/차단 위험이 있어 서버에서 주기적으로
 * 가져와 우리 DB에 저장 후 /api/market-quotes 로 서빙.
 */
class FetchMarketQuotes extends Command
{
    protected $signature   = 'market:fetch';
    protected $description = '주요 지수(나스닥/다우/S&P) + 관심종목 시세 수집';

    private array $indices = [
        '^IXIC' => '나스닥',
        '^DJI'  => '다우존스',
        '^GSPC' => 'S&P 500',
        '^KS11' => '코스피',
    ];

    private array $watchlist = [
        '005930.KS' => '삼성전자',
        '000660.KS' => 'SK하이닉스',
        'AAPL'      => 'Apple',
        'TSLA'      => 'Tesla',
        'NVDA'      => 'NVIDIA',
    ];

    public function handle(): int
    {
        $order = 0;
        foreach ($this->indices as $symbol => $name) {
            $this->fetchAndSave($symbol, $name, 'index', $order++);
        }
        $order = 0;
        foreach ($this->watchlist as $symbol => $name) {
            $this->fetchAndSave($symbol, $name, 'watchlist', $order++);
        }
        return self::SUCCESS;
    }

    private function fetchAndSave(string $symbol, string $name, string $category, int $order): void
    {
        try {
            $res = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->timeout(10)
                ->get("https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol), [
                    'range' => '1mo', 'interval' => '1d',
                ]);
            if (!$res->successful()) {
                $this->warn("[{$name}] 요청 실패: HTTP {$res->status()}");
                return;
            }
            $result = $res->json('chart.result.0');
            if (!$result) {
                $this->warn("[{$name}] 데이터 없음");
                return;
            }
            $meta = $result['meta'] ?? [];
            $closes = array_values(array_filter(
                $result['indicators']['quote'][0]['close'] ?? [],
                fn($v) => $v !== null
            ));

            MarketQuote::updateOrCreate(
                ['symbol' => $symbol],
                [
                    'name' => $name,
                    'category' => $category,
                    'price' => $meta['regularMarketPrice'] ?? null,
                    'change_pct' => isset($meta['regularMarketPrice'], $meta['chartPreviousClose']) && $meta['chartPreviousClose']
                        ? round((($meta['regularMarketPrice'] - $meta['chartPreviousClose']) / $meta['chartPreviousClose']) * 100, 3)
                        : null,
                    'change' => isset($meta['regularMarketPrice'], $meta['chartPreviousClose'])
                        ? round($meta['regularMarketPrice'] - $meta['chartPreviousClose'], 2)
                        : null,
                    'volume' => $meta['regularMarketVolume'] ?? null,
                    'sparkline' => array_slice($closes, -20),
                    'sort_order' => $order,
                ]
            );
            $this->info("[{$name}] {$meta['regularMarketPrice']} 저장 완료");
        } catch (\Throwable $e) {
            $this->warn("[{$name}] 오류: " . $e->getMessage());
        }
    }
}
