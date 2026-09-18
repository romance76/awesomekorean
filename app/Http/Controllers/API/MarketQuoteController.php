<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MarketQuote;

class MarketQuoteController extends Controller
{
    public function index()
    {
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
