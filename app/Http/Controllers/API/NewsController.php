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
        $query = News::select('id', 'title', 'source', 'image_url', 'local_image', 'link', 'category_id', 'view_count', 'published_at', 'created_at')
            ->with('category:id,name,slug')
            ->when($request->category_id, fn($q, $v) => $q->where('category_id', $v))
            ->when($request->search, fn($q, $v) => $q->where('title', 'like', "%{$v}%"));

        $sort = $request->sort ?? 'latest';
        if ($sort === 'popular') $query->orderByDesc('view_count');
        else $query->orderByDesc('published_at')->orderByDesc('id');

        $query->distinct();

        $paginated = $query->paginate(10);
        $paginated->getCollection()->transform(function ($n) {
            // 로컬 이미지 우선 → 없으면 외부 URL 썸네일
            if ($n->local_image) {
                $n->thumbnail_url = $n->local_image;
            } else {
                if ($n->image_url) {
                    $n->image_url = html_entity_decode($n->image_url, ENT_QUOTES | ENT_HTML5);
                }
                $n->thumbnail_url = $n->image_url ? ThumbHelper::url($n->image_url, 200) : null;
            }
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
