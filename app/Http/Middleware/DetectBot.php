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
     * - 비로그인 읽기(GET/HEAD): IP당 분당 1500회 — 홈 화면 1회가 API 26개를 부르고, 같은 IP(통신사 공용 IP·매장/사무실 와이파이)의
     *   여러 방문자가 한도를 나눠 쓰므로 300회로는 비로그인 화면이 통째로 비어 보였음
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

        // 3) auth 엔드포인트는 별도 (낮은) 제한 — brute force 방지용
        $path = $request->path();
        $isAuthEndpoint = in_array($path, [
            'api/login', 'api/register', 'api/password/reset', 'api/password/email',
            'api/forgot-password', 'api/verify-email',
        ]);

        $isRead = $request->isMethod('GET') || $request->isMethod('HEAD');
        $limit = $isAuthEndpoint ? 20 : ($isRead ? 1500 : 300);
        $rateLimitKey = ($isAuthEndpoint ? 'auth_rate:' : ($isRead ? 'api_read:' : 'api_rate:')) . $ip;
        $currentCount = (int) Cache::get($rateLimitKey, 0);

        if ($currentCount >= $limit) {
            return response()->json([
                'success' => false,
                'message' => '너무 많은 요청입니다. 잠시 후 다시 시도하세요.',
                'retry_after' => 60,
            ], 429);
        }

        if ($currentCount === 0) {
            Cache::put($rateLimitKey, 1, 60);
        } else {
            Cache::increment($rateLimitKey);
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
