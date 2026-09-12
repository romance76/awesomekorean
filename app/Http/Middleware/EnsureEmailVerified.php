<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 이메일 미인증 회원의 글쓰기를 막는 정책 게이트.
 * 가입은 그대로 허용하고 인증 메일만 발송하던 기존 동작(PR #51)에서
 * "인증 여부로 어떤 기능을 제한할지"는 별도 정책 결정으로 남겨뒀던 부분 —
 * 사용자 결정에 따라 글쓰기(게시판 신규 작성)에 한해 인증을 요구한다.
 */
class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => '이메일 인증 후 글쓰기가 가능합니다. 가입 시 발송된 인증 메일을 확인해주세요.',
            ], 403);
        }

        return $next($request);
    }
}
