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
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-ink min-h-screen">
    @include('info._header')

    <div class="max-w-7xl mx-auto px-4 py-5">
        <div class="hidden lg:flex items-center justify-between mb-4 flex-wrap gap-2">
            <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
                <span class="icon-chip w-9 h-9 bg-sky-50 text-sky-600">📘</span>
                정보
            </h1>
        </div>

        <div class="grid grid-cols-12 gap-4">
            {{-- 왼쪽: 카테고리 --}}
            <div class="col-span-12 lg:col-span-2 hidden lg:block">
                <div class="sticky top-20 space-y-3">
                    <div class="card overflow-hidden">
                        <div class="px-3 py-2.5 border-b border-gray-50 font-bold text-xs text-ink flex items-center gap-1.5">📋 카테고리</div>
                        <a href="{{ route('info.index') }}" class="block px-3 py-2 text-xs transition-colors {{ !$activeCategory ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50' }}">전체</a>
                        @foreach ($categories as $cat)
                            <a href="{{ route('info.index', ['category' => $cat]) }}" class="block px-3 py-2 text-xs transition-colors {{ $activeCategory === $cat ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50' }}">{{ $cat }}</a>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- 메인: 정보 글 목록 --}}
            <div class="col-span-12 lg:col-span-10">
                <div class="mb-2">
                    <span class="font-bold text-amber-700 text-sm">{{ $activeCategory ?: '전체' }}</span>
                    @unless ($activeCategory)
                        <span class="text-xs text-ink-muted ml-2">모든 정보 글을 볼 수 있습니다</span>
                    @endunless
                </div>

                @if ($posts->isEmpty())
                    <div class="py-16 text-center">
                        <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3 text-2xl">📘</div>
                        <p class="text-sm text-ink-muted">아직 등록된 글이 없습니다</p>
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach ($posts as $post)
                            <a href="{{ route('info.show', $post->slug) }}" class="card card-hover overflow-hidden cursor-pointer block">
                                <div class="flex gap-3 p-3">
                                    <div class="w-24 h-16 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0 flex items-center justify-center text-gray-300">
                                        @if ($post->cover_image_url)
                                            <img src="{{ $post->cover_image_url }}" alt="{{ $post->title }}" loading="lazy" class="w-full h-full object-cover">
                                        @else
                                            <span class="text-xl">📘</span>
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5 mb-1">
                                            <span class="badge-primary !text-[11px] !px-2">{{ $post->category }}</span>
                                        </div>
                                        <div class="text-sm font-semibold text-ink line-clamp-2 leading-snug">{{ $post->title }}</div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-xs text-ink-muted">👁 {{ $post->view_count }} · {{ $post->published_at->format('Y.m.d') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-6">{{ $posts->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
