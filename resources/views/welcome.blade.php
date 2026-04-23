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
    <div id="app"></div>
</body>
</html>
