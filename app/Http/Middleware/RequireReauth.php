<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * 위험한 관리자 동작(키 변경·열람, 결제 설정, 회원 삭제·대행 로그인·비밀번호 초기화) 앞에서
 * "비밀번호를 방금 다시 입력했는지(10분 이내)"를 확인한다.
 * 확인이 없으면 428 + code=reauth_required 로 응답하고, 관리자 화면이 비밀번호 입력창을 띄운 뒤 같은 요청을 다시 보낸다.
 * 재확인은 POST /api/admin/reauth (AdminReauthController) 에서 한다.
 */
class RequireReauth
{
    public const TTL_SECONDS = 600;

    public static function key(int $userId): string
    {
        return 'admin_reauth:' . $userId;
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => '인증이 필요합니다.'], 401);
        }
        if (!Cache::has(self::key($user->id))) {
            return response()->json([
                'success' => false,
                'code' => 'reauth_required',
                'message' => '안전을 위해 비밀번호를 한 번 더 입력해 주세요. (10분 동안 유효)',
            ], 428);
        }
        return $next($request);
    }
}
