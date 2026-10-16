<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\EarningsEvent;
use App\Models\MarketQuote;
use App\Services\EarningsCalendar;
use App\Services\YahooQuotes;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    private const MAX_WATCH = 30;

    // ── 내 관심종목 ───────────────────────────────────────────
    public function watchlist(Request $request, YahooQuotes $yahoo)
    {
        // 담아 둔 종목 중 시세가 5분 넘게 묵었으면 응답 후에 새로 받는다 (서버 스케줄러가 드물게 도는 환경 대비)
        $uid = $request->user()->id;
        $syms = DB::table('user_watchlists')->where('user_id', $uid)->pluck('symbol');
        $fresh = MarketQuote::whereIn('symbol', $syms)->where('updated_at', '>=', now()->subMinutes(5))->pluck('symbol');
        $stale = $syms->diff($fresh)->values()->all();
        if ($stale && Cache::add("user-quote-refresh-{$uid}", 1, 60)) {
            dispatch(fn() => $yahoo->refreshMany($stale))->afterResponse();
        }
        return response()->json(['success' => true, 'data' => $this->watchRows($request->user()->id)]);
    }

    public function addWatch(Request $request, YahooQuotes $yahoo)
    {
        $symbol = strtoupper(trim((string) $request->input('symbol')));
        if (!preg_match('/^[A-Z0-9.\-^=]{1,12}$/', $symbol)) {
            return response()->json(['success' => false, 'message' => '티커를 영문/숫자로 입력해 주세요 (예: AAPL)'], 422);
        }
        $uid = $request->user()->id;
        if (DB::table('user_watchlists')->where('user_id', $uid)->where('symbol', $symbol)->exists()) {
            return response()->json(['success' => false, 'message' => '이미 담아 둔 종목이에요'], 409);
        }
        if (DB::table('user_watchlists')->where('user_id', $uid)->count() >= self::MAX_WATCH) {
            return response()->json(['success' => false, 'message' => '관심종목은 최대 ' . self::MAX_WATCH . '개까지 담을 수 있어요'], 422);
        }
        $quote = MarketQuote::where('symbol', $symbol)->first();
        if (!$quote) {
            $quote = $yahoo->fetchAndSave($symbol, null, 'user');
            if (!$quote) return response()->json(['success' => false, 'message' => "'{$symbol}' 티커를 찾을 수 없어요"], 404);
        }
        DB::table('user_watchlists')->insert([
            'user_id' => $uid, 'symbol' => $symbol, 'name' => $quote->name,
            'sort_order' => (int) DB::table('user_watchlists')->where('user_id', $uid)->max('sort_order') + 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['success' => true, 'data' => $this->watchRows($uid)]);
    }

    public function removeWatch(Request $request, string $symbol)
    {
        DB::table('user_watchlists')->where('user_id', $request->user()->id)->where('symbol', strtoupper($symbol))->delete();
        return response()->json(['success' => true, 'data' => $this->watchRows($request->user()->id)]);
    }

    private function watchRows(int $uid)
    {
        $rows = DB::table('user_watchlists')->where('user_id', $uid)->orderBy('sort_order')->get();
        $quotes = MarketQuote::whereIn('symbol', $rows->pluck('symbol'))->get()->keyBy('symbol');
        return $rows->map(function ($r) use ($quotes) {
            $q = $quotes->get($r->symbol);
            return [
                'symbol' => $r->symbol, 'name' => $q->name ?? $r->name ?? $r->symbol,
                'price' => $q?->price, 'change' => $q?->change, 'change_pct' => $q?->change_pct,
                'sparkline' => $q?->sparkline ?? [], 'quoted_at' => $q?->quoted_at?->toISOString(),
            ];
        })->values();
    }

    // ── 회사 이름으로 티커 찾기 ───────────────────────────────
    // 한글 회사 이름은 Yahoo 검색이 못 알아들어서, 자주 찾는 종목만 미리 짝지어 둔다 (그 밖의 한글은 결과 없음)
    private const KO_ALIASES = [
        '애플' => 'AAPL', '마이크로소프트' => 'MSFT', '엔비디아' => 'NVDA', '테슬라' => 'TSLA', '아마존' => 'AMZN',
        '구글' => 'GOOGL', '알파벳' => 'GOOGL', '메타' => 'META', '페이스북' => 'META', '넷플릭스' => 'NFLX',
        '인텔' => 'INTC', '에이엠디' => 'AMD', '팔란티어' => 'PLTR', '브로드컴' => 'AVGO', '마이크론' => 'MU',
        '티에스엠씨' => 'TSM', '퀄컴' => 'QCOM', '오라클' => 'ORCL', '어도비' => 'ADBE', '세일즈포스' => 'CRM',
        '코카콜라' => 'KO', '펩시' => 'PEP', '스타벅스' => 'SBUX', '맥도날드' => 'MCD', '월마트' => 'WMT',
        '코스트코' => 'COST', '타겟' => 'TGT', '홈디포' => 'HD', '나이키' => 'NKE', '디즈니' => 'DIS',
        '비자' => 'V', '마스터카드' => 'MA', '페이팔' => 'PYPL', '존슨앤존슨' => 'JNJ', '화이자' => 'PFE',
        '보잉' => 'BA', '엑슨모빌' => 'XOM', '쉐브론' => 'CVX', '제이피모건' => 'JPM', '뱅크오브아메리카' => 'BAC',
        '버크셔' => 'BRK-B', '우버' => 'UBER', '에어비앤비' => 'ABNB', '쿠팡' => 'CPNG', '포드' => 'F',
        '지엠' => 'GM', '리비안' => 'RIVN', '코인베이스' => 'COIN', '로빈후드' => 'HOOD', '스노우플레이크' => 'SNOW',
        '스페이스엑스' => 'SPCX', '에스앤피' => 'SPY', '나스닥' => 'QQQ', '다우' => 'DIA',
    ];

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 1 || mb_strlen($q) > 40) return response()->json(['success' => true, 'data' => []]);
        $key = 'stock-search-' . md5(mb_strtolower($q));
        $rows = Cache::remember($key, 600, function () use ($q) {
            $out = [];
            // 한글이 들어 있으면 짝지어 둔 이름에서 먼저 찾는다 (띄어쓰기 무시)
            if (preg_match('/[가-힣]/u', $q)) {
                $needle = str_replace(' ', '', $q);
                foreach (self::KO_ALIASES as $ko => $sym) {
                    if (mb_strpos($ko, $needle) !== false || mb_strpos($needle, $ko) !== false) $out[$sym] = ['symbol' => $sym, 'name' => $ko, 'exchange' => ''];
                }
                // 짝지어 둔 이름으로 찾은 종목은 영문 이름·거래소를 Yahoo 로 보충
                $q2 = $out ? implode(' ', array_slice(array_keys($out), 0, 1)) : null;
                if (!$q2) return array_values($out);
                $q = $q2;
            }
            try {
                $res = \Illuminate\Support\Facades\Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])->timeout(8)
                    ->get('https://query1.finance.yahoo.com/v1/finance/search', ['q' => $q, 'quotesCount' => 10, 'newsCount' => 0, 'listsCount' => 0]);
                foreach (($res->successful() ? ($res->json('quotes') ?? []) : []) as $r) {
                    $sym = $r['symbol'] ?? null;
                    if (!$sym || !in_array($r['quoteType'] ?? '', ['EQUITY', 'ETF'], true)) continue;
                    if (!preg_match('/^[A-Z0-9.\-]{1,10}$/', $sym)) continue;      // 외국 거래소(005930.KS 등)·선물·지수 제외
                    if (str_contains($sym, '.')) continue;
                    $ko = array_search($sym, self::KO_ALIASES, true);
                    $out[$sym] = ['symbol' => $sym, 'name' => $r['shortname'] ?? $r['longname'] ?? $sym, 'exchange' => $r['exchDisp'] ?? '', 'ko' => $ko ?: null] + ($out[$sym] ?? []);
                }
            } catch (\Throwable $e) {}
            return array_slice(array_values($out), 0, 8);
        });
        return response()->json(['success' => true, 'data' => $rows]);
    }

    // ── 어닝 캘린더 ───────────────────────────────────────────
    public function earnings(Request $request)
    {
        $offset = max(-2, min(3, (int) $request->query('week', 0)));
        $monday = EarningsCalendar::mondayOf($offset);
        $friday = $monday->copy()->addDays(4);

        $has = EarningsEvent::whereBetween('report_date', [$monday->toDateString(), $friday->toDateString()])->exists();
        if (!$has && Cache::add("earnings-sync-{$monday->toDateString()}", 1, 600)) {
            app(EarningsCalendar::class)->syncWeek($monday);   // 비어 있으면 처음 한 번 바로 채운다
        }

        // 일정이 12시간 넘게 묵었으면 응답 후에 새로 받는다 (실적 시간/날짜 변경 반영)
        $newest = EarningsEvent::whereBetween('report_date', [$monday->toDateString(), $friday->toDateString()])->max('updated_at');
        if ($has && $newest && Carbon::parse($newest)->lt(now()->subHours(12)) && Cache::add("earnings-refresh-{$monday->toDateString()}", 1, 1800)) {
            dispatch(fn() => app(EarningsCalendar::class)->syncWeek($monday))->afterResponse();
        }

        $events = EarningsEvent::whereBetween('report_date', [$monday->toDateString(), $friday->toDateString()])
            ->orderByRaw('market_cap IS NULL')->orderByDesc('market_cap')->get()->groupBy(fn($e) => $e->report_date->toDateString());

        $today = Carbon::now('America/New_York')->toDateString();
        $days = [];
        for ($i = 0; $i < 5; $i++) {
            $d = $monday->copy()->addDays($i);
            $list = $events->get($d->toDateString(), collect());
            $days[] = [
                'date' => $d->toDateString(),
                'weekday' => ['월', '화', '수', '목', '금'][$i],
                'is_today' => $d->toDateString() === $today,
                'total' => $list->count(),
                'items' => $list->take(80)->map(fn($e) => [
                    'symbol' => $e->symbol, 'name' => $e->name, 'slot' => $e->time_slot, 'market_cap' => $e->market_cap,
                    'eps_forecast' => $e->eps_forecast, 'last_year_eps' => $e->last_year_eps,
                    'fiscal_quarter' => $e->fiscal_quarter, 'est_count' => $e->est_count,
                ])->values(),
            ];
        }
        return response()->json(['success' => true, 'data' => [
            'week' => $offset, 'from' => $monday->toDateString(), 'to' => $friday->toDateString(), 'days' => $days,
            'timezone' => 'America/New_York',
        ]]);
    }
}
