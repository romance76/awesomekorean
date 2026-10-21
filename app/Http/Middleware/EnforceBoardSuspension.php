<?php

namespace App\Http\Middleware;

use App\Support\BoardSuspensions;
use App\Support\SiteFlags;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 신고로 게시판 이용이 정지된 회원은 그 게시판의 API(보기·쓰기 모두)가 403 + code=board_suspended 로 막힌다.
 * 화면은 이 응답을 받으면 "이 게시판은 신고로 인하여 정지되어 사용할 수 없습니다" 안내를 띄운다.
 *
 * 게시판 주소가 아닌 요청은 첫 검사에서 바로 통과하고(비용 없음), 운영진(운영자·관리자·최고관리자)과 비회원은 막지 않는다.
 */
class EnforceBoardSuspension
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!BoardSuspensions::watches($request->segment(2)) || $request->segment(1) !== 'api') return $next($request);

        try {
            $user = auth('api')->user();
        } catch (\Throwable $e) {
            $user = null;
        }
        if (!$user || SiteFlags::isStaff($user)) return $next($request);

        // 정지 기록이 없는 회원(대부분)은 캐시된 빈 목록으로 바로 통과
        if (!BoardSuspensions::activeFor((int) $user->id)) return $next($request);

        $hit = BoardSuspensions::blockFor((int) $user->id, BoardSuspensions::keyForRequest($request));
        if ($hit) return response()->json(BoardSuspensions::payload($hit), 403);

        return $next($request);
    }
}
