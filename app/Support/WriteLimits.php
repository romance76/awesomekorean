<?php

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

/**
 * 새 글 등록(커뮤니티·Q&A·구인·장터·부동산·업소·동호회·레시피·공동구매·행사·숏츠·이력서) 전용 횟수 제한.
 * 전체 요청 한도(회원당 분당 400회)만 있으면 도배가 가능해서, 글 등록만 따로 좁게 건다 (2026-10-14 사이트 점검 B-6, 사용자 승인).
 *  - 회원당 1분에 5회, 하루(24시간)에 50회 — 모든 글 종류를 합쳐서 센다.
 *  - 관리자·운영진은 제외(공지·정보 글을 연달아 올려야 하므로).
 *  - 수정·삭제·댓글·좋아요는 이 제한과 무관(수정은 글을 늘리지 않음).
 * routes/api.php 의 등록 라우트에 `throttle:content-write` 로 붙고, 규칙은 bootstrap/app.php 에서 등록한다.
 */
class WriteLimits
{
    public const PER_MINUTE = 5;
    public const PER_DAY = 50;
    public const EXEMPT_ROLES = ['admin', 'super_admin', 'moderator'];

    public const MSG_MINUTE = '글을 너무 빠르게 올리고 있어요. 1분쯤 뒤에 다시 시도해 주세요.';
    public const MSG_DAY = '하루에 올릴 수 있는 글 수(50개)를 넘었어요. 내일 다시 올려 주세요.';

    /** 한도 규칙 목록. 비회원이면 IP 로 센다(등록 라우트는 로그인 필요라 사실상 예비용). */
    public static function limits(?int $userId, ?string $role, ?string $ip): array
    {
        if ($role !== null && in_array($role, self::EXEMPT_ROLES, true)) return [Limit::none()];
        $who = $userId ? 'u:' . $userId : 'ip:' . ($ip ?? '0');
        return [
            Limit::perMinute(self::PER_MINUTE)->by('write-m:' . $who)->response(fn (Request $r, array $h) => self::tooMany(self::MSG_MINUTE, $h)),
            Limit::perDay(self::PER_DAY)->by('write-d:' . $who)->response(fn (Request $r, array $h) => self::tooMany(self::MSG_DAY, $h)),
        ];
    }

    private static function tooMany(string $message, array $headers)
    {
        return response()->json(['success' => false, 'message' => $message], 429, $headers);
    }

    /** RateLimiter::for('content-write', ...) 에 넘기는 함수 */
    public static function resolver(): \Closure
    {
        return function (Request $request) {
            $uid = null; $role = null;
            try {
                $user = auth('api')->user();   // 서명이 맞는 토큰만 회원으로 인정
                if ($user) { $uid = (int) $user->id; $role = (string) $user->role; }
            } catch (\Throwable $e) {}
            return self::limits($uid, $role, $request->ip());
        };
    }
}
