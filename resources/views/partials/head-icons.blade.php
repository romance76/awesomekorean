{{-- 바탕화면/홈 화면 바로가기·탭·PWA 아이콘 — 관리자 사이트 설정에서 올린 아이콘을 사용.
     SPA(welcome)와 서버 렌더 페이지(/info 등)가 같은 태그를 쓰도록 공용 partial. --}}
<link rel="manifest" href="/manifest.json">
<link rel="icon" type="image/png" sizes="32x32" href="{{ \App\Support\BrandIcons::favicon() }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ \App\Support\BrandIcons::icon(192) }}">
<link rel="shortcut icon" href="{{ \App\Support\BrandIcons::favicon() }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ \App\Support\BrandIcons::appleTouch() }}">
<meta name="theme-color" content="#FFFFFF">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="어썸코리안">
