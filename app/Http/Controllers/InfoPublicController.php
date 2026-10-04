<?php

namespace App\Http\Controllers;

use App\Models\InfoPost;
use App\Models\SiteSetting;
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
            ->when($request->q, fn($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->orderByDesc('published_at')
            ->paginate(20)
            ->withQueryString();

        // 오른쪽 사이드바 "많이 본/최신 정보" 위젯 — 카테고리/검색 필터와 무관하게 전체 기준
        $popular = InfoPost::published()->orderByDesc('view_count')->limit(10)->get(['title', 'slug']);
        $latest = InfoPost::published()->orderByDesc('published_at')->limit(10)->get(['title', 'slug']);

        return view('info.index', [
            'posts' => $posts,
            'categories' => InfoPost::CATEGORIES,
            'activeCategory' => $request->category,
            'search' => $request->q,
            'popular' => $popular,
            'latest' => $latest,
            'viewMode' => $this->menuViewMode(),
        ]);
    }

    // 관리자 페이지 "메뉴 구성"에서 '정보' 항목의 기본 보기(목록/사진)를 바꾸면
    // 반영되도록 — site_settings.menu_config에 저장된 defaultView를 그대로 사용.
    private function menuViewMode(): string
    {
        $raw = SiteSetting::where('key', 'menu_config')->value('value');
        $menus = $raw ? json_decode($raw, true) : null;
        if (!is_array($menus)) return 'list';

        $info = collect($menus)->firstWhere('key', 'info');
        return ($info['defaultView'] ?? 'list') === 'card' ? 'card' : 'list';
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
