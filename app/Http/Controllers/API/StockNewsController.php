<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\StockNews;
use Illuminate\Http\Request;

// 증권 페이지 "증권가 뉴스" — 제목·짧은 요약·원문 링크만 내려준다
class StockNewsController extends Controller
{
    public function index(Request $request)
    {
        $limit = min(max((int) ($request->limit ?? 20), 1), 50);
        return response()->json([
            'success' => true,
            'data' => StockNews::orderByDesc('published_at')->limit($limit)
                ->get(['id', 'source', 'title', 'summary', 'url', 'image_url', 'lang', 'published_at']),
        ]);
    }
}
