<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AdminCollectService;
use Illuminate\Http\Request;

/**
 * 관리자 "지금 수집" 버튼 (음악·뉴스·숏츠). 요청 안에서 외부 API 를 부르지 않고
 * 큐 작업(RunAdminCollect)으로 넘긴 뒤 바로 {success, queued, message} 로 응답한다.
 * 레시피 동기화는 AdminRecipeController 가 같은 서비스로 넘긴다.
 *
 * 권한(AdminTier): 시작 POST 는 최고관리자(3), collect-status 조회는 관리자(2) 이상.
 */
class AdminCollectController extends Controller
{
    public function __construct(private AdminCollectService $collect) {}

    // POST /api/admin/fetch-music
    public function music(Request $request)
    {
        return $this->collect->begin('music', [], $request->user()?->id);
    }

    // POST /api/admin/fetch-news
    public function news(Request $request)
    {
        return $this->collect->begin('news', [], $request->user()?->id);
    }

    // POST /api/admin/fetch-shorts
    public function shorts(Request $request)
    {
        return $this->collect->begin('shorts', [], $request->user()?->id);
    }

    // GET /api/admin/collect-status — 종류별 진행 여부·시작 시각·마지막 결과 (Cache 만 읽음)
    public function status()
    {
        return response()->json([
            'success'     => true,
            'server_time' => now()->toIso8601String(),
            'data'        => $this->collect->status(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache');
    }
}
