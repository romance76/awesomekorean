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
    public function watchlist(Request $request)
    {
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
