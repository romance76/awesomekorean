<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class UpdateLastActive
{
    public function handle(Request $request, Closure $next)
    {
        if ($user = $request->user()) {
            // 정지된 계정은 남아 있는 토큰으로도 사용 불가 (정지 즉시 효력)
            if ($user->is_banned && !$request->is('api/logout')) {
                return response()->json(['success' => false, 'message' => '정지된 계정입니다.'], 403);
            }
            // 5분마다만 업데이트 (성능) — Carbon 3 diffInMinutes 부호 변경으로
            // abs() 없이는 과거 시각 비교 시 항상 음수가 나와 최초 1회 이후
            // 영원히 업데이트가 안 되던 버그였음.
            if (!$user->last_active_at || abs(now()->diffInMinutes($user->last_active_at)) >= 5) {
                $user->timestamps = false;
                $user->update(['last_active_at' => now()]);
            }
        }
        return $next($request);
    }
}
