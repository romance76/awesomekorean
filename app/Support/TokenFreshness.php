<?php

namespace App\Support;

use App\Models\User;

/**
 * 로그인 토큰이 "비밀번호 변경 전에 발급된 것"인지 판단한다.
 * 토큰의 발급 시각(iat)이 사용자의 password_changed_at 보다 이르면 오래된(stale) 토큰이다.
 * 판단에 필요한 값을 읽지 못하면 막지 않는다(멀쩡한 사용자를 로그아웃시키는 쪽의 실수를 피함).
 */
class TokenFreshness
{
    /** @param object|null $guard payload() 를 가진 JWT 가드 */
    public static function isStale(User $user, $guard = null): bool
    {
        if (!$user->password_changed_at) return false;
        try {
            $iat = (int) $guard->payload()->get('iat');
        } catch (\Throwable $e) {
            return false;
        }
        return $iat > 0 && $iat < $user->password_changed_at->timestamp;
    }

    /** 이미 파싱된 토큰 payload 의 iat 로 판단 (갱신 전에 사용) */
    public static function isStaleIat(User $user, int $iat): bool
    {
        return $user->password_changed_at && $iat > 0 && $iat < $user->password_changed_at->timestamp;
    }
}
