<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ $post->meta_title ?: $post->title }} — AwesomeKorean</title>
    <meta name="description" content="{{ $post->meta_description ?: $post->excerpt }}">
    <link rel="canonical" href="{{ route('info.show', $post->slug) }}">
    <meta property="og:type" content="article">
    <meta property="og:locale" content="ko_KR">
    <meta property="og:site_name" content="AwesomeKorean">
    <meta property="og:title" content="{{ $post->meta_title ?: $post->title }}">
    <meta property="og:description" content="{{ $post->meta_description ?: $post->excerpt }}">
    <meta property="og:url" content="{{ route('info.show', $post->slug) }}">
    @if ($post->cover_image_url)
        <meta property="og:image" content="{{ $post->cover_image_url }}">
    @endif
    <meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">
    <meta property="article:section" content="{{ $post->category }}">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
    @vite(['resources/css/app.css'])
    <style>
        /* 본문 HTML은 h2/p/ul/li/a/img만 포함(자동 생성 콘텐츠 규칙) — 타이포그래피
           플러그인 없이 최소한의 기본 스타일만 직접 지정 */
        .info-body h2 { font-size: 1.25rem; font-weight: 800; margin-top: 2rem; margin-bottom: 0.75rem; }
        .info-body p { margin-bottom: 1rem; line-height: 1.75; color: #1B1613; }
        .info-body ul { list-style: disc; padding-left: 1.5rem; margin-bottom: 1rem; }
        .info-body li { margin-bottom: 0.375rem; line-height: 1.6; }
        .info-body a { color: #F23D5C; text-decoration: underline; }
        .info-body img { max-width: 100%; border-radius: 8px; }
        .info-body em { color: #8A8178; font-size: 0.8125rem; }
    </style>
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post->title,
        'description' => $post->meta_description ?: $post->excerpt,
        'image' => $post->cover_image_url ? [$post->cover_image_url] : [],
        'datePublished' => $post->published_at->toIso8601String(),
        'dateModified' => $post->updated_at->toIso8601String(),
        'author' => ['@type' => 'Organization', 'name' => 'AwesomeKorean'],
        'publisher' => ['@type' => 'Organization', 'name' => 'AwesomeKorean'],
        'mainEntityOfPage' => route('info.show', $post->slug),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
</head>
<body class="bg-white text-ink min-h-screen">
    @include('info._header')

    <div class="max-w-7xl mx-auto px-4 py-5">
        <div class="hidden lg:flex items-center justify-between mb-4 flex-wrap gap-2">
            <a href="{{ route('info.index') }}" class="flex items-center gap-2.5 text-xl font-bold text-ink hover:text-amber-600 transition-colors">
                <span class="icon-chip w-9 h-9 bg-sky-50 text-sky-600">📘</span>
                정보
            </a>
            <form action="{{ route('info.index') }}" method="GET" class="flex gap-1">
                <input type="text" name="q" placeholder="정보 검색..." class="input-soft w-40 px-3 py-1.5 text-sm">
                <button type="submit" class="btn-primary px-3 py-1.5 text-xs">검색</button>
            </form>
        </div>
        <a href="{{ route('info.index') }}" class="btn-ghost mb-3 !px-2 inline-flex items-center gap-1.5 lg:hidden">← 정보 목록</a>

        <div class="grid grid-cols-12 gap-4">
            {{-- 왼쪽: 카테고리 --}}
            <div class="col-span-12 lg:col-span-2 hidden lg:block">
                <div class="card overflow-hidden sticky top-20">
                    <div class="px-3 py-2.5 border-b border-gray-50 font-bold text-xs text-ink flex items-center gap-1.5">📋 카테고리</div>
                    <a href="{{ route('info.index') }}" class="block px-3 py-2 text-xs text-ink-light hover:bg-amber-50/50 transition-colors">전체</a>
                    @foreach (\App\Models\InfoPost::CATEGORIES as $cat)
                        <a href="{{ route('info.index', ['category' => $cat]) }}"
                           class="block px-3 py-2 text-xs transition-colors {{ $post->category === $cat ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50' }}">{{ $cat }}</a>
                    @endforeach
                </div>
            </div>

            {{-- 메인: 글 본문 --}}
            <div class="col-span-12 lg:col-span-7">
                <div class="card overflow-hidden max-w-3xl">
                    <div class="px-5 py-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="badge-primary">{{ $post->category }}</span>
                        </div>
                        <h1 class="text-lg font-bold text-ink leading-snug">{{ $post->title }}</h1>
                        <div class="flex items-center gap-3 mt-2 text-xs text-ink-muted">
                            <span>{{ $post->published_at->format('Y년 n월 j일') }}</span>
                            <span class="flex items-center gap-1">👁 {{ number_format($post->view_count) }}회</span>
                        </div>
                    </div>
                    <div class="info-body px-5 py-5 border-t border-gray-50 text-sm leading-relaxed">
                        {!! $post->body !!}
                    </div>
                </div>

                {{-- 이전글 / 목록 / 다음글 --}}
                <div class="mt-4 flex items-stretch card text-sm overflow-hidden max-w-3xl">
                    @if ($prev)
                        <a href="{{ route('info.show', $prev->slug) }}" class="flex-1 min-w-0 px-4 py-3 hover:bg-amber-50 text-left text-ink-light border-r border-gray-50 transition-colors">
                            <div class="text-ink-muted text-xs">← 이전글</div>
                            <div class="text-xs text-ink-light truncate mt-0.5">{{ $prev->title }}</div>
                        </a>
                    @else
                        <div class="flex-1 min-w-0 px-4 py-3 text-left text-ink-faint border-r border-gray-50 text-xs">← 이전글 없음</div>
                    @endif

                    <a href="{{ route('info.index') }}" class="px-5 py-3 hover:bg-amber-50 text-center text-ink font-bold border-r border-gray-50 flex-shrink-0 transition-colors">목록</a>

                    @if ($next)
                        <a href="{{ route('info.show', $next->slug) }}" class="flex-1 min-w-0 px-4 py-3 hover:bg-amber-50 text-right text-ink-light transition-colors">
                            <div class="text-ink-muted text-xs">다음글 →</div>
                            <div class="text-xs text-ink-light truncate mt-0.5">{{ $next->title }}</div>
                        </a>
                    @else
                        <div class="flex-1 min-w-0 px-4 py-3 text-right text-ink-faint text-xs">다음글 없음</div>
                    @endif
                </div>
            </div>

            {{-- 오른쪽: 많이 본/최신 정보 (목록 페이지와 동일 — JS 없이 순수 CSS 라디오 탭) --}}
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
                    color: #d97706; border-bottom: 2px solid #fbbf24; background: rgba(255,247,237,.6);
                }
            </style>
        </div>
    </div>
</body>
</html>
