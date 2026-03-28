<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index(Request $request)
    {
        $query = News::query()->orderByDesc('published_at');

        if ($request->filled('category') && $request->category !== '전체') {
            $query->where('category', $request->category);
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 20);
        $news = $query->paginate($perPage);

        return response()->json([
            'data'         => $news->items(),
            'current_page' => $news->currentPage(),
            'last_page'    => $news->lastPage(),
            'total'        => $news->total(),
        ]);
    }

    public function show($id)
    {
        $news = News::findOrFail($id);
        return response()->json($news);
    }
}
