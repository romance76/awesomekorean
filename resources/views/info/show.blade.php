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
        .info-body a { color: #FF5A1F; text-decoration: underline; }
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
<body class="bg-white text-ink">
    <header class="border-b border-line">
        <div class="max-w-3xl mx-auto px-4 h-14 flex items-center justify-between">
            <a href="/" class="font-extrabold text-lg" style="color:#FF5A1F">AWESOME KOREAN</a>
            <a href="{{ route('info.index') }}" class="text-sm text-ink-light hover:text-ink">← 정보 목록</a>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-8">
        <a href="{{ route('info.index', ['category' => $post->category]) }}" class="text-xs font-bold" style="color:#FF5A1F">{{ $post->category }}</a>
        <h1 class="text-2xl font-extrabold mt-2 mb-3">{{ $post->title }}</h1>
        <time class="text-xs text-ink-faint">{{ $post->published_at->format('Y년 n월 j일') }} · 조회 {{ number_format($post->view_count) }}</time>

        <article class="info-body mt-6">
            {!! $post->body !!}
        </article>

        <nav class="mt-10 border-t border-line pt-6 grid grid-cols-2 gap-4 text-sm">
            <div>
                @if ($prev)
                    <a href="{{ route('info.show', $prev->slug) }}" class="text-ink-light hover:text-ink">← {{ $prev->title }}</a>
                @endif
            </div>
            <div class="text-right">
                @if ($next)
                    <a href="{{ route('info.show', $next->slug) }}" class="text-ink-light hover:text-ink">{{ $next->title }} →</a>
                @endif
            </div>
        </nav>
    </main>
</body>
</html>
