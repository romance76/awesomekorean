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
     * IP 차단 체크 미들웨어 (Phase 2-C Post: CIDR 지원).
     * - 단일 IP: 정확히 일치
     * - CIDR: '1.2.3.0/24' 형태 대역 매칭
     * - 만료: 레코드 삭제 후 통과
     * - 5분 캐시 (IP 단위)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $cacheKey = "ip_ban:{$ip}";

        try {
            $banStatus = Cache::remember($cacheKey, 300, function () use ($ip) {
                // 1) 정확 일치 단일 IP
                $exact = DB::table('ip_bans')
                    ->where('ip_address', $ip)
                    ->first();

                if ($exact) {
                    if ($exact->expires_at && now()->greaterThan($exact->expires_at)) {
                        DB::table('ip_bans')->where('id', $exact->id)->delete();
                    } else {
                        return [
                            'banned' => true,
                            'reason' => $exact->reason ?? '접근이 차단되었습니다.',
                            'expires_at' => $exact->expires_at,
                        ];
                    }
                }

                // 2) CIDR 범위 매칭
                if (\Schema::hasColumn('ip_bans', 'is_cidr')) {
                    $cidrs = DB::table('ip_bans')
                        ->where('is_cidr', true)
                        ->get();

                    foreach ($cidrs as $row) {
                        if ($row->expires_at && now()->greaterThan($row->expires_at)) {
                            DB::table('ip_bans')->where('id', $row->id)->delete();
                            continue;
                        }
                        if ($this->ipInCidr($ip, $row->ip_address)) {
                            return [
                                'banned' => true,
                                'reason' => $row->reason ?? '대역 차단',
                                'expires_at' => $row->expires_at,
                            ];
                        }
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
            // 테이블 없음 등 예외 시 통과
        }

        return $next($request);
    }

    /**
     * IP 가 CIDR 범위 안에 있는지. IPv4 만 지원.
     */
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) return false;
        [$subnet, $mask] = explode('/', $cidr);
        $mask = (int) $mask;
        if ($mask < 0 || $mask > 32) return false;

        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) return false;

        $maskLong = $mask === 0 ? 0 : (~((1 << (32 - $mask)) - 1)) & 0xFFFFFFFF;
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
