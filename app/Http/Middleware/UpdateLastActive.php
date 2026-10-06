<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class UpdateLastActive
{
    public function handle(Request $request, Closure $next)
    {
        if ($user = $request->user()) {
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
