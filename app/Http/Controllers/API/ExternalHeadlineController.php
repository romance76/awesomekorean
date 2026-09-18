<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ExternalHeadline;
use Illuminate\Http\Request;

class ExternalHeadlineController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) ($request->per_page ?? 20), 40);
        return response()->json([
            'success' => true,
            'data' => ExternalHeadline::whereNotNull('image_url')
                ->whereNotNull('summary')
                ->where('summary', '!=', '')
                ->orderByDesc('published_at')
                ->limit($perPage)
                ->get(),
        ]);
    }

    // 클릭 시 언론사 사이트로 바로 튕기지 않고, 우리 사이트 안에서 제목/
    // 썸네일/요약을 먼저 보여주는 중간 페이지용. 대부분의 언론사 RSS는
    // 본문 전체 재배포 권한이 없어(오마이뉴스 제외) 요약까지만 표시.
    public function show($id)
    {
        return response()->json(['success' => true, 'data' => ExternalHeadline::findOrFail($id)]);
    }
}
