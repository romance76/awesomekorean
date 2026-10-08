<?php
use App\Http\Controllers\InfoPublicController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// '정보' 탭은 검색엔진 노출이 목적이라, Vue SPA 캐치올(welcome.blade.php, 정적
// title/description 하나뿐)이 아니라 서버사이드 Blade로 직접 렌더링한다.
Route::get('/info', [InfoPublicController::class, 'index'])->name('info.index');
Route::get('/info/{slug}', [InfoPublicController::class, 'show'])->name('info.show');
Route::get('/sitemap.xml', [SitemapController::class, 'index']);
// 같은 내용을 다른 주소로도 제공 — Search Console 이 sitemap.xml 을 'Couldn't fetch' 로 붙잡고 있을 때 새로 제출하기 위함
Route::get('/sitemap-main.xml', [SitemapController::class, 'index']);

// Amazon 제휴 상품 클릭 리다이렉트 — 클릭 집계 후 실제 Amazon 상품 페이지로 이동.
// SPA 캐치올보다 먼저 등록해야 매칭됨.
Route::get('/go/amazon/{id}', [\App\Http\Controllers\API\ShoppingController::class, 'go']);

// Google/Amazon 소셜 로그인. routes/api.php가 아니라 여기 두는 이유는
// SocialAuthController 상단 주석 참고(세션 기반 state CSRF 검증을 쓰기 위함).
Route::get('/auth/{provider}/redirect', [\App\Http\Controllers\SocialAuthController::class, 'redirect'])
    ->whereIn('provider', ['google', 'amazon']);
Route::get('/auth/{provider}/callback', [\App\Http\Controllers\SocialAuthController::class, 'callback'])
    ->whereIn('provider', ['google', 'amazon']);

// 바로가기/탭/PWA 아이콘 — 관리자 사이트 설정에서 업로드한 아이콘을 사용 (public/ 에 정적 파일을
// 두면 웹서버가 먼저 가로채 업로드와 무관한 빈 파일/구버전이 나가므로 라우트로 처리)
Route::get('/manifest.json', fn () => response()->json(\App\Support\BrandIcons::manifest())
    ->header('Content-Type', 'application/manifest+json')
    ->header('Cache-Control', 'public, max-age=3600'));
Route::get('/favicon.ico', fn () => response()->file(\App\Support\BrandIcons::faviconFile(), [
    'Content-Type' => 'image/png',
    'Cache-Control' => 'public, max-age=3600',
]));

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
