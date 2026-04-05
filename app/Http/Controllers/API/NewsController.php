<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\NewsCategory;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index(Request $request)
    {
        $query = News::with('category:id,name,slug')
            ->when($request->category_id, fn($q, $v) => $q->where('category_id', $v))
            ->when($request->search, fn($q, $v) => $q->where('title', 'like', "%{$v}%"));

        $sort = $request->sort ?? 'latest';
        if ($sort === 'popular') $query->orderByDesc('view_count');
        else $query->orderByDesc('published_at');

        return response()->json(['success' => true, 'data' => $query->paginate(20)]);
    }

    public function show($id)
    {
        $news = News::with('category:id,name,slug')->findOrFail($id);
        $news->increment('view_count');
        return response()->json(['success' => true, 'data' => $news]);
    }

    public function categories()
    {
        $cats = NewsCategory::whereNull('parent_id')->with('children')->orderBy('id')->get();
        return response()->json(['success' => true, 'data' => $cats]);
    }
}
