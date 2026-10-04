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
            'menus' => $this->headerMenus(),
        ]);
    }

    // 관리자 페이지 "메뉴 구성"에서 '정보' 항목의 기본 보기(목록/사진)를 바꾸면
    // 반영되도록 — site_settings.menu_config에 저장된 defaultView를 그대로 사용.
    private function menuViewMode(): string
    {
        $menus = $this->rawMenuConfig();
        if (!$menus) return 'list';

        $info = collect($menus)->firstWhere('key', 'info');
        return ($info['defaultView'] ?? 'list') === 'card' ? 'card' : 'list';
    }

    // NavBar.vue(홈)와 동일하게 site_settings.menu_config의 저장된 순서/활성화
    // 상태를 그대로 따라야 함 — 예전엔 _header.blade.php에 메뉴 목록이 하드코딩돼
    // 있어서 관리자 페이지에서 순서를 바꾸거나 메뉴를 껐다 켜도 /info 헤더에는
    // 반영이 안 되는 버그가 있었음.
    private function headerMenus(): array
    {
        $menus = $this->rawMenuConfig() ?: self::DEFAULT_MENUS;

        return collect($menus)
            ->filter(fn($m) => ($m['enabled'] ?? true) !== false)
            ->sortBy(fn($m, $i) => $m['order'] ?? $i)
            ->map(fn($m) => [
                'key' => $m['key'],
                'label' => $m['label'] ?? $m['key'],
                'path' => $m['path'] ?? "/{$m['key']}",
            ])
            ->values()
            ->all();
    }

    private function rawMenuConfig(): ?array
    {
        $raw = SiteSetting::where('key', 'menu_config')->value('value');
        $menus = $raw ? json_decode($raw, true) : null;
        return is_array($menus) ? $menus : null;
    }

    private const DEFAULT_MENUS = [
        ['key' => 'home', 'label' => '홈', 'path' => '/'],
        ['key' => 'community', 'label' => '커뮤니티', 'path' => '/community'],
        ['key' => 'qa', 'label' => 'Q&A', 'path' => '/qa'],
        ['key' => 'jobs', 'label' => '구인구직', 'path' => '/jobs'],
        ['key' => 'market', 'label' => '중고장터', 'path' => '/market'],
        ['key' => 'realestate', 'label' => '부동산', 'path' => '/realestate'],
        ['key' => 'directory', 'label' => '업소록', 'path' => '/directory'],
        ['key' => 'clubs', 'label' => '동호회', 'path' => '/clubs'],
        ['key' => 'news', 'label' => '뉴스', 'path' => '/news'],
        ['key' => 'recipes', 'label' => '레시피', 'path' => '/recipes'],
        ['key' => 'groupbuy', 'label' => '공동구매', 'path' => '/groupbuy'],
        ['key' => 'chat', 'label' => '채팅', 'path' => '/chat'],
        ['key' => 'games', 'label' => '게임', 'path' => '/games'],
        ['key' => 'shorts', 'label' => '숏츠', 'path' => '/shorts'],
        ['key' => 'elder', 'label' => '안심서비스', 'path' => '/elder'],
        ['key' => 'events', 'label' => '이벤트', 'path' => '/events'],
        ['key' => 'friends', 'label' => '친구', 'path' => '/friends'],
        ['key' => 'music', 'label' => '음악듣기', 'path' => '/music'],
        ['key' => 'info', 'label' => '정보', 'path' => '/info'],
        ['key' => 'shopping', 'label' => '쇼핑', 'path' => '/shopping'],
        ['key' => 'comms', 'label' => '안심 커뮤', 'path' => '/comms'],
    ];

    public function show(string $slug)
    {
        $post = InfoPost::published()->where('slug', $slug)->firstOrFail();
        $post->increment('view_count');

        $prev = InfoPost::published()->where('published_at', '<', $post->published_at)->orderByDesc('published_at')->first();
        $next = InfoPost::published()->where('published_at', '>', $post->published_at)->orderBy('published_at')->first();

        $menus = $this->headerMenus();
        return view('info.show', compact('post', 'prev', 'next', 'menus'));
    }
}
