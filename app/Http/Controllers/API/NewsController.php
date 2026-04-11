<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\NewsCategory;
use App\Support\ThumbHelper;
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
        else $query->orderByDesc('published_at')->orderByDesc('id');

        // 중복 제거
        $query->distinct();

        $paginated = $query->paginate(20);
        $paginated->getCollection()->transform(function ($n) {
            $n->thumbnail_url = ThumbHelper::url($n->image_url, 200);
            return $n;
        });
        return response()->json(['success' => true, 'data' => $paginated]);
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
