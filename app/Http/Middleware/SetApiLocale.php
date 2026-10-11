<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * API 요청의 입력 오류 메시지를 한국어(lang/ko/validation.php)로 낸다.
 * 서버 .env 의 APP_LOCALE 값과 상관없이 API 에서만 적용하고, 웹(메일 등)은 건드리지 않는다.
 * 컨트롤러가 직접 쓴 한국어 메시지는 그대로 우선한다.
 */
class SetApiLocale
{
    public function handle(Request $request, Closure $next)
    {
        app()->setLocale('ko');
        return $next($request);
    }
}
