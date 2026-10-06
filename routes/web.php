<?php
use App\Http\Controllers\InfoPublicController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// '정보' 탭은 검색엔진 노출이 목적이라, Vue SPA 캐치올(welcome.blade.php, 정적
// title/description 하나뿐)이 아니라 서버사이드 Blade로 직접 렌더링한다.
Route::get('/info', [InfoPublicController::class, 'index'])->name('info.index');
Route::get('/info/{slug}', [InfoPublicController::class, 'show'])->name('info.show');
Route::get('/sitemap.xml', [SitemapController::class, 'index']);

// Amazon 제휴 상품 클릭 리다이렉트 — 클릭 집계 후 실제 Amazon 상품 페이지로 이동.
// SPA 캐치올보다 먼저 등록해야 매칭됨.
Route::get('/go/amazon/{id}', [\App\Http\Controllers\API\ShoppingController::class, 'go']);

// Google/Amazon 소셜 로그인. routes/api.php가 아니라 여기 두는 이유는
// SocialAuthController 상단 주석 참고(세션 기반 state CSRF 검증을 쓰기 위함).
Route::get('/auth/{provider}/redirect', [\App\Http\Controllers\SocialAuthController::class, 'redirect'])
    ->whereIn('provider', ['google', 'amazon']);
Route::get('/auth/{provider}/callback', [\App\Http\Controllers\SocialAuthController::class, 'callback'])
    ->whereIn('provider', ['google', 'amazon']);

Route::get('/{any}', function () {
    // apple-touch-icon은 <link> 태그로 명시해야 안전함(아이콘이
    // storage/app/public 심볼릭 링크 경로에 있어 iOS의 암묵적
    // /apple-touch-icon.png 자동탐색 규칙으로는 못 찾음). 업로드 시 함께
    // 저장된 캐시 버스터(?v=...)를 재사용해서 교체 직후에도 바로 반영되게 함.
    $appIconSetting = \App\Models\SiteSetting::where('key', 'app_icon_url')->value('value');
    $version = $appIconSetting && str_contains($appIconSetting, '?v=')
        ? substr($appIconSetting, strpos($appIconSetting, '?v='))
        : '';
    $appIconUrl = '/storage/branding/apple-touch-icon.png' . $version;

    // 커스텀 파비콘을 업로드 안 했으면 기존 정적 /favicon.ico로 폴백
    $faviconUrl = \App\Models\SiteSetting::where('key', 'favicon_url')->value('value') ?: '/favicon.ico';

    return view('welcome', ['appIconUrl' => $appIconUrl, 'faviconUrl' => $faviconUrl]);
})->where('any', '.*');
