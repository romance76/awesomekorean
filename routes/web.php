<?php
use App\Http\Controllers\InfoPublicController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// '정보' 탭은 검색엔진 노출이 목적이라, Vue SPA 캐치올(welcome.blade.php, 정적
// title/description 하나뿐)이 아니라 서버사이드 Blade로 직접 렌더링한다.
Route::get('/info', [InfoPublicController::class, 'index'])->name('info.index');
Route::get('/info/{slug}', [InfoPublicController::class, 'show'])->name('info.show');
Route::get('/sitemap.xml', [SitemapController::class, 'index']);

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
