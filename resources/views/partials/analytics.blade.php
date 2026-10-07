{{-- 구글 애널리틱스(GA4): 관리자 > 설정 > API 키 관리에서 service=google_analytics 로 등록하면 켜짐 --}}
@php($__gaId = \App\Support\Analytics::measurementId())
@if($__gaId)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $__gaId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ $__gaId }}');
    </script>
@endif
