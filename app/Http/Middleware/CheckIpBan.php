<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckIpBan
{
    /**
     * IP 차단 체크 미들웨어
     * - 요청 IP가 ip_bans 테이블에 있는지 확인
     * - 만료된 차단: 레코드 삭제 후 통과
     * - 유효한 차단: 403 응답
     * - 5분 캐시로 매 요청마다 DB 조회 방지
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $cacheKey = "ip_ban:{$ip}";

        try {
            // 캐시에서 차단 상태 확인 (5분 TTL)
            $banStatus = Cache::remember($cacheKey, 300, function () use ($ip) {
                // 이 IP 하나로 등록된 차단 + 대역(CIDR, "/" 가 들어간 것) 차단을 함께 확인
                $bans = DB::table('ip_bans')
                    ->where('ip_address', $ip)
                    ->orWhere('ip_address', 'like', '%/%')
                    ->get();

                foreach ($bans as $ban) {
                    // 만료 확인 — 만료된 차단 레코드는 지우고 건너뜀
                    if ($ban->expires_at && now()->greaterThan($ban->expires_at)) {
                        DB::table('ip_bans')->where('id', $ban->id)->delete();
                        continue;
                    }
                    if (\App\Models\IpBan::matches((string) $ban->ip_address, $ip)) {
                        return [
                            'banned' => true,
                            'reason' => $ban->reason ?? '접근이 차단되었습니다.',
                            'expires_at' => $ban->expires_at,
                        ];
                    }
                }
                return ['banned' => false];
            });

            if ($banStatus['banned']) {
                return response()->json([
                    'success' => false,
                    'message' => '접근이 차단되었습니다.',
                    'reason'  => $banStatus['reason'] ?? null,
                    'expires_at' => $banStatus['expires_at'] ?? null,
                ], 403);
            }
        } catch (\Exception $e) {
            // ip_bans 테이블이 없으면 무시하고 통과
        }

        return $next($request);
    }
}
