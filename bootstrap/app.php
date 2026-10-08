<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 미들웨어 별칭 등록
        $middleware->alias([
            'admin'        => \App\Http\Middleware\AdminMiddleware::class,
            'role'         => \App\Http\Middleware\EnsureRole::class, // P2B-5
            'check.ip.ban' => \App\Http\Middleware\CheckIpBan::class,
            'detect.bot'   => \App\Http\Middleware\DetectBot::class,
            'auth'         => \App\Http\Middleware\Authenticate::class,
            'cache.api'    => \App\Http\Middleware\CacheApiResponse::class,
            'verified.email' => \App\Http\Middleware\EnsureEmailVerified::class,
            'ingest.auth'  => \App\Http\Middleware\IngestAuth::class,
        ]);

        // www 주소로 들어온 읽기 요청은 대표 주소(APP_URL)로 301 이동 — 같은 사이트가 두 주소로 보이는 것 방지
        $middleware->prepend(\App\Http\Middleware\CanonicalHost::class);

        // 모든 응답(웹/API)에 보안 헤더
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // IP 차단 + 봇 감지 + 온라인 상태 미들웨어를 API 전체에 적용
        $middleware->api(prepend: [
            \App\Http\Middleware\CheckIpBan::class,
            \App\Http\Middleware\DetectBot::class,
        ]);
        $middleware->api(append: [
            \App\Http\Middleware\UpdateLastActive::class,
        ]);
        // API 전체 요청 수 제한(아래 'api' 규칙) — 정의만 있고 실제로 적용되지 않던 것을 켠다
        $middleware->throttleApi();
    })
    ->booted(function () {
        RateLimiter::for('api', function (Request $request) {
            // 로그인한 회원은 계정별로 넉넉히(통화/채팅 폴링 포함), 비회원은 IP별로 제한
            $uid = null;
            try { $uid = auth('api')->id(); } catch (\Throwable $e) {}   // 서명이 맞는 토큰만 회원으로 인정 (가짜 토큰으로 제한 우회 방지)
            if ($uid) return Limit::perMinute(400)->by('u:' . $uid);
            return Limit::perMinute(300)->by($request->ip());
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            // Accept 헤더가 없는 클라이언트(SPA 외 연동)는 expectsJson()이 false가 되어
            // 422 JSON 대신 홈으로 302 리다이렉트를 받던 문제 — api/* 요청은 항상 JSON으로 응답.
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors'  => $e->errors(),
                ], $e->status);
            }
        });
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => '요청이 너무 많습니다. 잠시 후 다시 시도하세요.'], 429);
            }
        });
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => '인증이 필요합니다.',
                ], 401);
            }
        });
    })->create();
