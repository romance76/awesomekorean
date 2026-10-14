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
    public const REPORT_ONLY_POLICY = "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' https://js.stripe.com https://translate.google.com https://translate.googleapis.com https://www.googletagmanager.com https://www.google-analytics.com https://pagead2.googlesyndication.com https://unpkg.com https://www.youtube.com https://s.ytimg.com; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://translate.googleapis.com; "
        . "img-src 'self' data: blob: https:; "
        . "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net; "
        . "connect-src 'self' wss://awesomekorean.com https://api.open-meteo.com https://air-quality-api.open-meteo.com https://api.zippopotam.us https://nominatim.openstreetmap.org https://*.google-analytics.com https://*.googleapis.com https://*.firebaseio.com https://api.stripe.com; "
        . "frame-src 'self' https://js.stripe.com https://hooks.stripe.com https://www.youtube.com https://www.youtube-nocookie.com https://www.google.com https://www.openstreetmap.org; "
        . "media-src 'self' blob: https:; worker-src 'self' blob:; manifest-src 'self'; "
        . "base-uri 'self'; object-src 'none'; form-action 'self'; report-uri /api/csp-report";

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
        // 전체 CSP 도입 준비: 아무것도 막지 않는 '보고 전용' 정책. 위반은 /api/csp-report 로 모여 storage/logs/csp-report.log 에 남는다.
        // 로그를 보고 빠진 출처를 정리한 뒤에 강제(Content-Security-Policy)로 올린다. HTML 화면에만 붙인다.
        if (str_contains((string) $h->get('Content-Type'), 'text/html')) {
            $h->set('Content-Security-Policy-Report-Only', self::REPORT_ONLY_POLICY);
        }
        if ($request->isSecure() || $request->header('X-Forwarded-Proto') === 'https') {
            $h->set('Strict-Transport-Security', 'max-age=2592000');   // 30일간 https 만 사용(인증서 갱신이 안정적인 것을 확인한 뒤 1년으로 늘린다). 하위 도메인은 제외
        }
        $h->remove('X-Powered-By');
        return $response;
    }
}
