<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AwesomeKorean — 미국 한인 커뮤니티</title>
    <meta name="description" content="미국 한인 커뮤니티 플랫폼. 커뮤니티, 구인구직, 중고장터, 한인 업소록을 한 곳에서.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#2563eb">
    <script>
    // 깨진 서비스워커 복구
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.getRegistrations().then(function(regs) {
        regs.forEach(function(r) { r.update().catch(function(){}); });
      });
    }
    </script>
</head>
<body>
    <div id="app"></div>
</body>
</html>
