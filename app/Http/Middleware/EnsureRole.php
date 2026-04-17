<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * P2B-5: role 기반 권한 체크 미들웨어.
 * Usage: ->middleware('role:admin,super_admin')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => '인증이 필요합니다'], 401);
        }
        if (!in_array($user->role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => '권한이 없습니다',
                'required_roles' => $roles,
            ], 403);
        }
        return $next($request);
    }
}
