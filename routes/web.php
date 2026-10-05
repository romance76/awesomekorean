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

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
