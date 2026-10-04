<?php

namespace App\Http\Controllers;

use App\Models\InfoPost;
use Illuminate\Http\Request;

// '정보' 탭 공개 페이지 — Vue SPA(단일 정적 title/description)와 달리, 검색엔진
// 노출이 목적이므로 일부러 서버사이드 Blade로 렌더링해 글마다 실제 title/meta
// description/OG 태그가 응답 HTML에 그대로 찍히도록 한다.
class InfoPublicController extends Controller
{
    public function index(Request $request)
    {
        $posts = InfoPost::published()
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            ->orderByDesc('published_at')
            ->paginate(20)
            ->withQueryString();

        return view('info.index', [
            'posts' => $posts,
            'categories' => InfoPost::CATEGORIES,
            'activeCategory' => $request->category,
        ]);
    }

    public function show(string $slug)
    {
        $post = InfoPost::published()->where('slug', $slug)->firstOrFail();
        $post->increment('view_count');

        $prev = InfoPost::published()->where('published_at', '<', $post->published_at)->orderByDesc('published_at')->first();
        $next = InfoPost::published()->where('published_at', '>', $post->published_at)->orderBy('published_at')->first();

        return view('info.show', compact('post', 'prev', 'next'));
    }
}
