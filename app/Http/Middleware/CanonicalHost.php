<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * www.awesomekorean.com 으로 들어온 읽기 요청(GET/HEAD)을 대표 주소(APP_URL, 예: https://awesomekorean.com)로 영구 이동(301)시킨다.
 * 같은 사이트가 두 주소로 열려 검색엔진/광고 심사에서 중복 주소로 보이는 것을 막기 위함.
 * - 대표 주소가 www 로 시작하거나, 로컬/IP 주소이면 아무것도 하지 않는다.
 * - POST 등 쓰기 요청은 이동시키지 않는다(본문이 사라지는 것을 막기 위해).
 */
class CanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $canonical = parse_url((string) config('app.url'));
        $host = strtolower((string) ($canonical['host'] ?? ''));
        $scheme = $canonical['scheme'] ?? 'https';

        if ($host === '' || str_starts_with($host, 'www.') || filter_var($host, FILTER_VALIDATE_IP) || !str_contains($host, '.')) {
            return $next($request);
        }

        if (strtolower($request->getHost()) === 'www.' . $host) {
            return redirect()->away($scheme . '://' . $host . $request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
