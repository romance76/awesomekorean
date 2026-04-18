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
            'admin.audit'  => \App\Http\Middleware\AdminAuditLog::class,
            'permission'   => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'spatie.role'  => \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);

        // IP 차단 + 봇 감지 + 온라인 상태 미들웨어를 API 전체에 적용
        $middleware->api(prepend: [
            \App\Http\Middleware\CheckIpBan::class,
            \App\Http\Middleware\DetectBot::class,
        ]);
        $middleware->api(append: [
            \App\Http\Middleware\UpdateLastActive::class,
        ]);
    })
    ->booted(function () {
        RateLimiter::for('api', function (Request $request) {
            if ($request->user()) return Limit::none();
            return Limit::perMinute(60)->by($request->ip());
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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
