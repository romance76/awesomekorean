<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 모든 응답에 브라우저 보안 헤더를 붙인다 (클릭재킹, MIME 스니핑, 평문 접속, 불필요한 권한 요청 방어).
 * CSP 는 사이트가 쓰는 외부 스크립트(광고 등)를 깨뜨리지 않도록 "안전한 부분"(프레임/base/form/object)만 강제한다.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;

        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // 마이크(통화)·위치(내 주변)는 우리 사이트만, 카메라/결제 등은 막는다
        $h->set('Permissions-Policy', 'camera=(), microphone=(self), geolocation=(self), payment=(), usb=(), interest-cohort=()');
        $h->set('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'");
        $h->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        if ($request->isSecure() || $request->header('X-Forwarded-Proto') === 'https') {
            $h->set('Strict-Transport-Security', 'max-age=2592000');   // 30일간 https 만 사용(인증서 갱신이 안정적인 것을 확인한 뒤 1년으로 늘린다). 하위 도메인은 제외
        }
        $h->remove('X-Powered-By');
        return $response;
    }
}
