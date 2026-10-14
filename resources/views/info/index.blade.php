<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ $activeCategory ? $activeCategory.' 정보' : '정보' }} — AwesomeKorean</title>
    <meta name="description" content="미국 한인을 위한 생활정보 가이드 — 이민·비자, 세금, 금융, 보험, 부동산, 교육 등 실제 검색 수요 기반 정보.">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="ko_KR">
    <meta property="og:site_name" content="AwesomeKorean">
    <meta property="og:title" content="{{ $activeCategory ? $activeCategory.' 정보' : '정보' }} — AwesomeKorean">
    <meta property="og:description" content="미국 한인을 위한 생활정보 가이드.">
    @include('partials.head-icons')
    @include('partials.analytics')
    @include('partials.adsense')
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-ink min-h-screen">
    @include('info._header')

    <div class="max-w-7xl mx-auto px-4 py-5">
        {{-- 헤더: 모바일 (커뮤니티 목록과 같은 자리) — 제목은 info/_title (GroupTitle.vue 와 같은 .grp-* 클래스) --}}
        <div class="lg:hidden mb-3">
            <div class="mb-2">@include('info._title', ['stack' => true])</div>
            <form action="{{ route('info.index') }}" method="GET" class="flex gap-1.5">
                @if ($activeCategory)
                    <input type="hidden" name="category" value="{{ $activeCategory }}">
                @endif
                <input type="text" name="q" value="{{ $search }}" placeholder="정보 검색..." class="input-soft flex-1 text-xs py-2">
                <button type="submit" class="btn-primary text-xs px-3 py-2">검색</button>
            </form>
            @if ($categories)
            <div class="flex gap-1.5 overflow-x-auto scrollbar-hide mt-2">
                <a href="{{ route('info.index') }}" class="badge {{ !$activeCategory ? 'bg-amber-50 text-amber-700' : 'bg-surface text-ink-light' }}">전체</a>
                @foreach ($categories as $cat)
                    <a href="{{ route('info.index', ['category' => $cat]) }}" class="badge {{ $activeCategory === $cat ? 'bg-amber-50 text-amber-700' : 'bg-surface text-ink-light' }}">{{ $cat }}</a>
                @endforeach
            </div>
            @endif
        </div>
        {{-- 헤더: 데스크탑 --}}
        <div class="hidden lg:flex items-center justify-between mb-4">
            @include('info._title', ['stack' => false])
            <form action="{{ route('info.index') }}" method="GET" class="flex items-center gap-2">
                @if ($activeCategory)
                    <input type="hidden" name="category" value="{{ $activeCategory }}">
                @endif
                <input type="text" name="q" value="{{ $search }}" placeholder="정보 검색..." class="input-soft w-48 text-xs py-1.5">
                <button type="submit" class="btn-primary px-4 py-2">검색</button>
            </form>
        </div>

        <div class="grid grid-cols-12 gap-4">
            {{-- 왼쪽: 카테고리 --}}
            <div class="col-span-12 lg:col-span-2 hidden lg:block">
                <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
                    <div class="card overflow-hidden">
                        <div class="px-3 py-2.5 border-b border-gray-50 flex items-center gap-2">
                            <span class="icon-chip w-7 h-7 bg-sky-50 text-sky-600 text-[13px]">📋</span>
                            <span class="text-xs font-bold text-ink">카테고리</span>
                        </div>
                        <a href="{{ route('info.index') }}" class="block px-3 py-2 text-xs transition-colors {{ !$activeCategory ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50' }}">전체</a>
                        @foreach ($categories as $cat)
                            <a href="{{ route('info.index', ['category' => $cat]) }}" class="block px-3 py-2 text-xs transition-colors {{ $activeCategory === $cat ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50' }}">{{ $cat }}</a>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- 메인: 정보 글 목록 --}}
            <div class="col-span-12 lg:col-span-7">
                <div class="mb-2">
                    <span class="font-bold text-amber-700 text-sm">{{ $activeCategory ?: '전체' }}</span>
                    @if ($search)
                        <span class="badge-gray ml-2">"{{ $search }}" 검색결과</span>
                    @elseif (!$activeCategory)
                        <span class="text-xs text-ink-muted ml-2">모든 정보 글을 볼 수 있습니다</span>
                    @endif
                </div>

                @if ($posts->isEmpty())
                    <div class="py-16 text-center">
                        <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3 text-2xl">📘</div>
                        <p class="text-sm text-ink-muted">아직 등록된 글이 없습니다</p>
                    </div>
                @elseif ($viewMode === 'card')
                    {{-- 사진뷰: 관리자 메뉴 설정에서 "정보"의 기본 보기를 카드(⊞)로 설정한 경우 --}}
                    <div class="grid sm:grid-cols-2 gap-3">
                        @foreach ($posts as $post)
                            <a href="{{ route('info.show', $post->slug) }}" class="card card-hover overflow-hidden cursor-pointer block">
                                <div class="w-full h-36 bg-gray-100 overflow-hidden flex items-center justify-center text-gray-300">
                                    @if ($post->cover_image_url)
                                        <img src="{{ $post->cover_image_url }}" alt="{{ $post->title }}" loading="lazy" class="w-full h-full object-cover">
                                    @else
                                        <span class="text-3xl">📘</span>
                                    @endif
                                </div>
                                <div class="p-3">
                                    <span class="badge-primary !text-[11px] !px-2">{{ $post->category }}</span>
                                    <div class="text-sm font-semibold text-ink line-clamp-2 leading-snug mt-1">{{ $post->title }}@if ($post->published_at && $post->published_at->gt(now()->subHours(48)))<span class="new-n" title="새 글" aria-label="새 글"><b>N</b></span>@endif</div>
                                    <span class="text-xs text-ink-muted mt-1 block">👁 {{ $post->view_count }} · {{ $post->published_at->format('Y.m.d') }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-6">{{ $posts->links('partials.pagination') }}</div>
                @else
                    {{-- 커뮤니티 목록(BoardList.vue)과 같은 줄 모양: 한 카드 안에 줄, 배지 → 제목 → 요약 → 정보 줄, 사진은 오른쪽 --}}
                    <div class="card overflow-hidden divide-y divide-[#DDD4CA]">
                        @foreach ($posts as $post)
                            <a href="{{ route('info.show', $post->slug) }}" class="list-row !px-4 sm:!px-5 !py-4 flex gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="badge-primary flex-shrink-0">{{ $post->category }}</span>
                                    </div>
                                    <div class="text-[15px] font-bold text-ink leading-snug mt-1 line-clamp-2 break-words">{{ $post->title }}@if ($post->published_at && $post->published_at->gt(now()->subHours(48)))<span class="new-n" title="새 글" aria-label="새 글"><b>N</b></span>@endif</div>
                                    @if ($post->excerpt)
                                        <p class="text-[13px] text-ink-light leading-relaxed mt-1 line-clamp-2 break-words">{{ \Illuminate\Support\Str::limit(strip_tags($post->excerpt), 160) }}</p>
                                    @endif
                                    <div class="flex flex-wrap items-center gap-x-2.5 sm:gap-x-3 gap-y-1 mt-2 text-xs text-ink-muted">
                                        <span>{{ $post->published_at->format('Y.m.d') }}</span>
                                        <span class="flex items-center gap-0.5 tabular-nums" aria-label="조회 {{ $post->view_count }}">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>{{ $post->view_count }}
                                        </span>
                                    </div>
                                </div>
                                @if ($post->cover_image_url)
                                    <div class="relative flex-shrink-0 self-start">
                                        <img src="{{ $post->cover_image_url }}" alt="" loading="lazy" decoding="async" class="w-[72px] h-[72px] sm:w-24 sm:h-24 rounded-xl object-cover bg-gray-100" onerror="this.parentElement.style.display='none'">
                                    </div>
                                @endif
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-6">{{ $posts->links('partials.pagination') }}</div>
                @endif
            </div>

            {{-- 오른쪽: 많이 본/최신 정보 (JS 없이 순수 CSS 라디오 탭) --}}
            <div class="col-span-12 lg:col-span-3 hidden lg:block">
                <div class="sticky top-20">
                    <div class="card overflow-hidden info-tabs">
                        <input type="radio" name="info-tab" id="tab-popular" class="hidden" checked>
                        <input type="radio" name="info-tab" id="tab-latest" class="hidden">
                        <div class="flex border-b border-gray-50">
                            <label for="tab-popular" class="tab-popular-label flex-1 py-2.5 text-xs font-bold text-center cursor-pointer transition text-ink-muted">많이 본 정보</label>
                            <label for="tab-latest" class="tab-latest-label flex-1 py-2.5 text-xs font-bold text-center cursor-pointer transition text-ink-muted">최신 정보</label>
                        </div>
                        <div class="tab-popular-panel py-1">
                            @forelse ($popular as $i => $p)
                                <a href="{{ route('info.show', $p->slug) }}" class="flex items-start gap-2 px-3 py-2 hover:bg-amber-50/40 transition-colors">
                                    <span class="text-xs font-bold flex-shrink-0 w-5 text-center {{ $i < 3 ? 'text-amber-600' : 'text-ink-faint' }}">{{ $i + 1 }}</span>
                                    <span class="text-xs text-ink-light leading-snug line-clamp-2">{{ $p->title }}</span>
                                </a>
                            @empty
                                <div class="py-4 text-center text-xs text-ink-muted">데이터가 없습니다</div>
                            @endforelse
                        </div>
                        <div class="tab-latest-panel py-1">
                            @forelse ($latest as $i => $p)
                                <a href="{{ route('info.show', $p->slug) }}" class="flex items-start gap-2 px-3 py-2 hover:bg-amber-50/40 transition-colors">
                                    <span class="text-xs font-bold flex-shrink-0 w-5 text-center {{ $i < 3 ? 'text-amber-600' : 'text-ink-faint' }}">{{ $i + 1 }}</span>
                                    <span class="text-xs text-ink-light leading-snug line-clamp-2">{{ $p->title }}</span>
                                </a>
                            @empty
                                <div class="py-4 text-center text-xs text-ink-muted">데이터가 없습니다</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
            <style>
                .info-tabs .tab-latest-panel { display: none; }
                .info-tabs:has(#tab-latest:checked) .tab-popular-panel { display: none; }
                .info-tabs:has(#tab-latest:checked) .tab-latest-panel { display: block; }
                .info-tabs:has(#tab-popular:checked) .tab-popular-label,
                .info-tabs:has(#tab-latest:checked) .tab-latest-label {
                    /* SidebarWidgets.vue 의 탭과 같은 색 (amber-600 · amber-400 · amber-50/60 = 브랜드 핑크 팔레트) */
                    color: #E0145A; border-bottom: 2px solid #FF4F88; background: rgba(255,240,245,.6);
                }
            </style>
        </div>
    </div>
    @include('info._footer')
</body>
</html>
