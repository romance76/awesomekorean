<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

// '정보' 탭 자동 생성 파이프라인 전용 — Authorization: Bearer <토큰> 검증.
// 일반 사용자 인증(Sanctum/세션)과 무관한 단일 고정 토큰 방식으로,
// info.awesomekorean.com의 ingest API와 같은 계약을 유지한다.
// .env(INFO_INGEST_TOKEN) 우선, 없으면 관리자 페이지 "API 키 관리"에 등록한
// api_keys 테이블(서비스 코드: info_ingest_token)을 fallback으로 사용 — SSH 없이도
// 관리자 UI에서 바로 등록/교체할 수 있도록.
class IngestAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('services.info_ingest.token');

        if (!$token) {
            try {
                $row = DB::table('api_keys')->where('service', 'info_ingest_token')->where('is_active', true)->first();
                $token = $row->api_key ?? null;
            } catch (\Exception $e) {}
        }

        if (!$token || !is_string($request->bearerToken()) || !hash_equals((string) $token, (string) $request->bearerToken())) {   // 시간차 공격 방지(상수 시간 비교)
            return response()->json(['success' => false, 'message' => '인증이 필요합니다.'], 401);
        }

        return $next($request);
    }
}
