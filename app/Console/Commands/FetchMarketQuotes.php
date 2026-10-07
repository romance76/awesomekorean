<?php

namespace App\Console\Commands;

use App\Models\MarketQuote;
use App\Services\YahooQuotes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 메인 화면 "인기 주식" 위젯 + 증권 페이지용 시세를 Yahoo Finance 에서 가져와 우리 DB에 저장한다.
 * (방문자 브라우저가 매번 외부 API 를 부르면 차단/느려짐 위험이 있어 서버가 주기적으로 받아 /api/market-quotes 로 서빙)
 * 기본 지수/종목 + 회원들이 관심종목으로 담아 둔 티커를 함께 갱신한다.
 */
class FetchMarketQuotes extends Command
{
    protected $signature   = 'market:fetch';
    protected $description = '미국 주요 지수/기본 종목 + 회원 관심종목 시세 수집';

    public function handle(YahooQuotes $yahoo): int
    {
        $n = 0;
        foreach (YahooQuotes::INDICES as $symbol => $name) {
            $this->report($symbol, $yahoo->fetchAndSave($symbol, $name, 'index', $n++));
        }
        $n = 0;
        foreach (YahooQuotes::WATCHLIST as $symbol => $name) {
            $this->report($symbol, $yahoo->fetchAndSave($symbol, $name, 'watchlist', $n++));
        }

        // 회원 관심종목 (기본 목록에 없는 것만, 한 번에 최대 200개)
        $defaults = array_merge(array_keys(YahooQuotes::INDICES), array_keys(YahooQuotes::WATCHLIST));
        $symbols = DB::table('user_watchlists')->select('symbol')->distinct()->whereNotIn('symbol', $defaults)->limit(200)->pluck('symbol');
        foreach ($symbols as $symbol) {
            $this->report($symbol, $yahoo->fetchAndSave($symbol, null, 'user'));
        }

        // 아무도 담지 않은 오래된 'user' 시세는 정리
        MarketQuote::where('category', 'user')->whereNotIn('symbol', DB::table('user_watchlists')->select('symbol'))->delete();
        return self::SUCCESS;
    }

    private function report(string $symbol, $q): void
    {
        $q ? $this->info("[{$symbol}] {$q->price} ({$q->change_pct}%)") : $this->warn("[{$symbol}] 가져오기 실패");
    }
}
