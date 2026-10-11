<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\SystemStatus;

/**
 * GET /api/admin/system-status — 최고관리자 전용 시스템 상태 요약 (AdminTier 규칙표: system-status → 3).
 * 값마다 따로 보호(일부가 실패해도 나머지는 내려줌), 무거운 값은 30초 캐시. 계산은 App\Support\SystemStatus.
 */
class AdminSystemStatusController extends Controller
{
    public function index()
    {
        return response()->json(['success' => true, 'data' => SystemStatus::snapshot()]);
    }
}
