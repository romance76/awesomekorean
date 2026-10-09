<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if(config('services.stripe.key'))<meta name="stripe-key" content="{{ config('services.stripe.key') }}">@endif
    <title>AwesomeKorean — 미국 한인 커뮤니티</title>
    <meta name="description" content="미국 한인 커뮤니티 플랫폼. 커뮤니티, 구인구직, 중고장터, 한인 업소록을 한 곳에서.">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
    @php
        try {
            $bootLogo = \App\Models\SiteSetting::where('key', 'logo_url')->value('value');
            $bootLogoDark = \App\Models\SiteSetting::where('key', 'logo_dark_url')->value('value');
        } catch (\Throwable $e) { $bootLogo = null; $bootLogoDark = null; }
    @endphp
    <script>window.__BOOT__ = @json(['logo_url' => $bootLogo ?: null, 'logo_dark_url' => $bootLogoDark ?: null]);</script>
    @if($bootLogo)<link rel="preload" as="image" href="{{ $bootLogo }}">@endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.head-icons')
    @include('partials.analytics')
    @include('partials.adsense')
    <style>
    /* Google Translate 상단 배너 및 UI 완전 숨김 (구/신 위젯 모두 대응) */
    .goog-te-banner-frame,
    .goog-te-banner-frame.skiptranslate,
    .goog-te-gadget,
    #goog-gt-tt,
    .goog-tooltip,
    .goog-tooltip:hover,
    iframe.VIpgJd-ZVi9od-ORHb-OEVmcd,
    iframe.VIpgJd-ZVi9od-ORHb,
    .VIpgJd-ZVi9od-ORHb-OEVmcd,
    .VIpgJd-ZVi9od-ORHb,
    .VIpgJd-yQoo3f-LgbsSe { display: none !important; visibility: hidden !important; }
    body { top: 0 !important; position: static !important; }
    html { margin-top: 0 !important; }
    .goog-text-highlight { background: transparent !important; box-shadow: none !important; }
    </style>
    <script>
    // 깨진 서비스워커 복구
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.getRegistrations().then(function(regs) {
        regs.forEach(function(r) { r.update().catch(function(){}); });
      });
    }
    // Google Translate init
    function googleTranslateElementInit() {
      new google.translate.TranslateElement({
        pageLanguage: 'ko',
        includedLanguages: 'en,ko,ja,zh-CN,es,vi',
        autoDisplay: false,
        layout: google.translate.TranslateElement.InlineLayout.SIMPLE
      }, 'google_translate_element');
    }
    </script>
    <script src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit" defer></script>
</head>
<body>
    <div id="google_translate_element" style="display:none"></div>
    <div id="app">
        {{-- 검색엔진/광고 심사 크롤러용 본문: 자바스크립트를 실행하지 못해도 사이트가 무엇인지, 어떤 글이 있는지 읽을 수 있게 함.
             Vue 앱이 마운트되면 이 내용은 앱 화면으로 교체됨 (이용자에게 보이는 내용과 같은 사이트 소개·링크). --}}
        @php
            try {
                $seoPosts = \Illuminate\Support\Facades\Cache::remember('welcome_seo_posts', 600, fn () =>
                    \App\Models\InfoPost::published()->orderByDesc('published_at')->limit(12)->get(['title', 'slug', 'excerpt']));
            } catch (\Throwable $e) { $seoPosts = collect(); }
        @endphp
        <main id="seo-fallback" style="position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);border:0">
            <h1 style="font-size:24px;margin:0 0 8px">AwesomeKorean — 미국 한인 커뮤니티</h1>
            <p style="margin:0 0 16px">어썸코리안은 미국에 사는 한인을 위한 커뮤니티입니다. 이민·비자, 세금, 보험, 부동산, 운전면허 같은 생활 정보와 Q&amp;A, 구인구직, 중고장터, 한인 업소록, 동호회, 뉴스, 공동구매를 한곳에서 볼 수 있습니다.</p>
            <nav aria-label="주요 메뉴" style="margin:0 0 16px">
                <a href="/info">정보</a> · <a href="/qa">Q&amp;A</a> · <a href="/community">커뮤니티</a> · <a href="/jobs">구인구직</a> ·
                <a href="/market">중고장터</a> · <a href="/realestate">부동산</a> · <a href="/directory">업소록</a> · <a href="/news">뉴스</a> ·
                <a href="/groupbuy">공동구매</a> · <a href="/events">이벤트</a> · <a href="/shopping">내돈내산 리뷰</a> ·
                <a href="/about">소개</a> · <a href="/contact">문의</a> · <a href="/privacy">개인정보처리방침</a> · <a href="/terms">이용약관</a>
            </nav>
            @if($seoPosts->count())
                <h2 style="font-size:18px;margin:0 0 8px">최신 정보 글</h2>
                <ul style="margin:0;padding-left:20px">
                    @foreach($seoPosts as $p)
                        <li><a href="/info/{{ $p->slug }}">{{ $p->title }}</a>@if($p->excerpt) — {{ \Illuminate\Support\Str::limit(strip_tags($p->excerpt), 90) }}@endif</li>
                    @endforeach
                </ul>
            @endif
        </main>
    </div>
</body>
</html>
