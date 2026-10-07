<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MarketQuote;

class MarketQuoteController extends Controller
{
    public function index(\App\Services\YahooQuotes $yahoo)
    {
        $yahoo->ensureFresh();   // 오래됐으면 새로 받는다 (서버 스케줄러가 드물게 도는 환경 대비)
        $quotes = MarketQuote::orderBy('sort_order')->get();
        return response()->json([
            'success' => true,
            'data' => [
                'indices' => $quotes->where('category', 'index')->values(),
                'watchlist' => $quotes->where('category', 'watchlist')->values(),
            ],
        ]);
    }
}
