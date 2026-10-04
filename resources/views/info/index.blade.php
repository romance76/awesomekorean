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
<body class="bg-white text-ink">
    <header class="border-b border-line">
        <div class="max-w-5xl mx-auto px-4 h-14 flex items-center justify-between">
            <a href="/" class="font-extrabold text-lg" style="color:#FF5A1F">AWESOME KOREAN</a>
            <a href="/" class="text-sm text-ink-light hover:text-ink">← 홈으로</a>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-extrabold mb-1">정보</h1>
        <p class="text-ink-light text-sm mb-6">미국 한인 생활정보 가이드 — 이민·비자, 세금, 금융, 보험, 부동산 등</p>

        <nav class="flex flex-wrap gap-2 mb-8">
            <a href="{{ route('info.index') }}"
               class="px-3 py-1.5 rounded-full text-sm font-semibold {{ !$activeCategory ? 'text-white' : 'bg-surface text-ink-light hover:text-ink' }}"
               @if(!$activeCategory) style="background-color:#FF5A1F" @endif>전체</a>
            @foreach ($categories as $cat)
                <a href="{{ route('info.index', ['category' => $cat]) }}"
                   class="px-3 py-1.5 rounded-full text-sm font-semibold {{ $activeCategory === $cat ? 'text-white' : 'bg-surface text-ink-light hover:text-ink' }}"
                   @if($activeCategory === $cat) style="background-color:#FF5A1F" @endif>{{ $cat }}</a>
            @endforeach
        </nav>

        @if ($posts->isEmpty())
            <p class="text-ink-light text-sm py-12 text-center">아직 등록된 글이 없습니다.</p>
        @else
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach ($posts as $post)
                    <a href="{{ route('info.show', $post->slug) }}" class="block border border-line rounded-xl overflow-hidden hover:shadow-md transition-shadow">
                        @if ($post->cover_image_url)
                            <img src="{{ $post->cover_image_url }}" alt="{{ $post->title }}" class="w-full h-40 object-cover" loading="lazy">
                        @endif
                        <div class="p-4">
                            <span class="text-xs font-bold" style="color:#FF5A1F">{{ $post->category }}</span>
                            <h2 class="font-bold text-sm mt-1 line-clamp-2">{{ $post->title }}</h2>
                            @if ($post->excerpt)
                                <p class="text-xs text-ink-light mt-1 line-clamp-2">{{ $post->excerpt }}</p>
                            @endif
                            <time class="text-xs text-ink-faint mt-2 block">{{ $post->published_at->format('Y.m.d') }}</time>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-8">{{ $posts->links() }}</div>
        @endif
    </main>
</body>
</html>
