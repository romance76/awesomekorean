{{-- 구글 애드센스: 사이트 소유 확인 + 광고 라이브러리 로드.
     실제 광고 노출 여부는 애드센스 승인 후 애드센스 화면의 자동 광고/광고 단위 설정에 따른다. --}}
@php($__adsClient = (string) config('services.adsense.client'))
@if(preg_match('/^ca-pub-\d{10,20}$/', $__adsClient))
    <meta name="google-adsense-account" content="{{ $__adsClient }}">
    {{-- 광고 코드는 광고를 붙여도 되는 화면에서만 불러온다 (로그인·관리자·개인 화면, 수집 콘텐츠 화면은 제외: App\Support\SeoHead::noAdsSections) --}}
    @if(\App\Support\SeoHead::adsAllowed(request()->path()))
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ $__adsClient }}" crossorigin="anonymous"></script>
    @endif
@endif
