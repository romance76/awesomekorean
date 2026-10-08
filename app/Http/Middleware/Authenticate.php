<?php
namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * 인증 뒤에 한 번 더: 비밀번호를 바꾼 시각(password_changed_at)보다 먼저 발급된 로그인 토큰은 거부한다.
     * (다른 기기·탈취된 토큰이 비밀번호를 바꿔도 살아 있던 문제 방지. 기존 회원은 NULL 이라 영향 없음)
     */
    protected function authenticate($request, array $guards)
    {
        parent::authenticate($request, $guards);

        foreach ($guards as $guard) {
            if ($guard !== 'api') continue;
            $user = auth('api')->user();
            if ($user && \App\Support\TokenFreshness::isStale($user, auth('api'))) {
                $this->unauthenticated($request, $guards);
            }
        }
    }

    protected function redirectTo(Request $request): ?string
    {
        return null;
    }

    protected function unauthenticated($request, array $guards)
    {
        abort(response()->json(['message' => '인증이 필요합니다.'], 401));
    }
}
