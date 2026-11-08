<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DetectBot
{
    /**
     * 봇 감지 및 레이트 리미팅 미들웨어
     * - 로그인된 유저: 무제한 (JWT 토큰 존재 시)
     * - 비로그인 일반 API(쓰기 등): IP당 분당 300회
     * - 비로그인 읽기(GET/HEAD): 앱에서는 제한하지 않는다 — 공개 페이지는 누구에게나 항상 보여야 하고(홈 1회가 API 26개),
     *   같은 IP를 쓰는 방문자(통신사 공용 IP·매장/사무실)나 서버가 보는 IP가 프록시 하나로 뭉쳐 보이는 경우에도
     *   한 곳이 한도를 채우면 모든 방문자의 화면이 빈 화면이 되기 때문. 과도한 수집(스크래퍼) 차단은 서버(nginx) 쪽 몫
     * - 로그인/회원가입/비밀번호 등 auth 엔드포인트: IP당 분당 20회 (brute force 방지)
     * - Honeypot: 'website_url' 히든 필드가 채워지면 차단
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        // 1) Honeypot 필드 체크 (모든 POST 요청)
        if ($request->isMethod('post') && $request->filled('website_url')) {
            $this->autoBanIp($ip, '자동 차단: Honeypot 필드 감지 (봇)', now()->addDay());
            return response()->json([
                'success' => false,
                'message' => '요청을 처리할 수 없습니다.',
            ], 403);
        }

        // 2) 로그인된 유저는 제한 없음 (JWT Bearer 토큰 존재 여부)
        if ($request->bearerToken()) {
            return $next($request);
        }

        // 2-1) 읽기(GET/HEAD)는 세지도 막지도 않는다 (위 설명 참고)
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return $next($request);
        }

        // 3) auth 엔드포인트는 별도 (낮은) 제한 — brute force 방지용
        $path = $request->path();
        $isAuthEndpoint = in_array($path, [
            'api/login', 'api/register', 'api/password/reset', 'api/password/email',
            'api/forgot-password', 'api/verify-email',
        ]);

        $limit = $isAuthEndpoint ? 20 : 300;
        // 분 단위 창(window)마다 키를 새로 만든다 — 예전엔 "읽고 → 0이면 60초 유효기간으로 저장, 아니면 +1" 이라
        // 홈 화면처럼 요청이 동시에 여러 개 올 때 유효기간이 없는 카운터가 생겨(Redis/파일 캐시에서 INCR 이 새 키를
        // 만들면 TTL 이 없음) 영원히 쌓이다가 그 IP 를 계속 429 로 막는 문제가 있었다. 이제는 키 이름이 분마다 바뀌고
        // (add 로 TTL 을 원자적으로 걸고 increment 로 +1) 혹시 TTL 이 빠져도 다음 분엔 다른 키라서 영향이 없다.
        $window = intdiv(time(), 60);
        $rateLimitKey = ($isAuthEndpoint ? 'auth_rate:' : 'api_rate:') . $ip . ':' . $window;
        Cache::add($rateLimitKey, 0, 120);
        $currentCount = (int) Cache::increment($rateLimitKey);

        if ($currentCount > $limit) {
            return response()->json([
                'success' => false,
                'message' => '너무 많은 요청입니다. 잠시 후 다시 시도하세요.',
                'retry_after' => max(1, 60 - (time() % 60)),
            ], 429);
        }

        return $next($request);
    }

    /**
     * IP 자동 차단 (ip_bans 테이블에 삽입)
     */
    private function autoBanIp(string $ip, string $reason, $expiresAt = null): void
    {
        try {
            DB::table('ip_bans')->insertOrIgnore([
                'ip_address' => $ip,
                'reason'     => $reason,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Cache::put("ip_ban:{$ip}", [
                'banned'     => true,
                'reason'     => $reason,
                'expires_at' => $expiresAt,
            ], 300);
        } catch (\Exception $e) {
            // ip_bans 테이블이 없으면 무시
        }
    }
}
