{{-- 구글 애드센스: 사이트 소유 확인 + 광고 라이브러리 로드.
     실제 광고 노출 여부는 애드센스 승인 후 애드센스 화면의 자동 광고/광고 단위 설정에 따른다. --}}
@php($__adsClient = (string) config('services.adsense.client'))
@if(preg_match('/^ca-pub-\d{10,20}$/', $__adsClient))
    <meta name="google-adsense-account" content="{{ $__adsClient }}">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ $__adsClient }}" crossorigin="anonymous"></script>
@endif
