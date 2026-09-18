<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Http\Request;

// 홈 화면 "언론사별 헤드라인" 위젯용 — 실제 데이터는 news 테이블(is_external=true)에서 가져온다.
// 상세 내용은 /api/news/{id} (NewsController::show)로 조회, 클릭 시 /news/{id}로 이동.
class ExternalHeadlineController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) ($request->per_page ?? 20), 40);
        return response()->json([
            'success' => true,
            'data' => News::where('is_external', true)
                ->whereNotNull('image_url')
                ->whereNotNull('summary')
                ->where('summary', '!=', '')
                ->orderByDesc('published_at')
                ->limit($perPage)
                ->get(['id', 'source', 'title', 'summary', 'source_url', 'image_url', 'published_at']),
        ]);
    }
}
