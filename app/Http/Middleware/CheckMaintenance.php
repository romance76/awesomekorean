<?php

namespace App\Http\Middleware;

use App\Support\SiteFlags;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 점검 모드 — 관리자 "사이트 설정 > 점검 모드" 가 켜져 있으면 일반 회원의 API 요청은 503 + 한국어 안내(JSON)로 막는다.
 *
 * 관리자가 스스로 잠기지 않도록 아래는 점검 중에도 항상 열려 있다:
 *  - 최고관리자·관리자·운영자로 로그인한 요청 (모든 API)
 *  - /api/admin/* (관리자 화면이 쓰는 API — 설정 저장 포함이라, 잘못 켜도 관리자에서 바로 끌 수 있다)
 *  - 로그인·로그아웃·토큰 갱신·비밀번호 찾기/재설정, 내 정보(/api/user), 공개 설정(/api/settings/public)
 *  - 자동 수집용(info-ingest, news-ingest) 주소
 *  - 상태 확인(/up)과 정적 파일, 화면(SPA) 자체 — 웹 쪽은 서버가 만드는 /info 글만 막는다
 *
 * 켜짐 여부는 SiteFlags 가 60초 캐시로 읽고, 설정이 저장되면 즉시 비워지므로 끄면 바로 풀린다.
 */
class CheckMaintenance
{
    // /api/ 뒤 주소 중 점검 중에도 여는 것 (Request::is 패턴)
    private const OPEN_API = [
        'admin', 'admin/*',
        'login', 'logout', 'auth/refresh', 'forgot-password', 'reset-password',
        'user',
        'settings/public',
        'info-ingest/*', 'news-ingest/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $m = SiteFlags::maintenance();
        if (!$m['on']) return $next($request);

        if ($request->is('api/*')) {
            foreach (self::OPEN_API as $p) {
                if ($request->is('api/' . $p)) return $next($request);
            }
            if ($this->isStaff()) return $next($request);

            return response()->json(SiteFlags::maintenancePayload(), 503, ['Retry-After' => '300']);
        }

        // 화면(SPA)은 열어 두고 안에서 안내를 보여 준다. 서버가 직접 만드는 '정보' 글만 점검 안내로 바꾼다(검색엔진에는 503 = 잠시 후 다시).
        if ($request->is('info', 'info/*')) {
            return response($this->html($m['message'], $m['until']), 503, ['Retry-After' => '300', 'Content-Type' => 'text/html; charset=UTF-8']);
        }

        return $next($request);
    }

    private function isStaff(): bool
    {
        try {
            return SiteFlags::isStaff(auth('api')->user());
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function html(string $message, ?string $until): string
    {
        $msg = e($message);
        $untilLine = $until ? '<p class="u">종료 예정: ' . e(str_replace('T', ' ', $until)) . '</p>' : '';
        return <<<HTML
<!DOCTYPE html>
<html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex"><title>점검 중</title>
<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#fffaf0;font-family:'Pretendard',-apple-system,'Malgun Gothic',sans-serif;color:#3b2f1e}
.b{max-width:420px;padding:32px 24px;text-align:center}.i{font-size:44px}h1{font-size:22px;margin:12px 0 8px}p{font-size:16px;line-height:1.6;margin:6px 0;white-space:pre-line}.u{color:#8a7a62;font-size:14px}a{color:#b45309}</style></head>
<body><div class="b"><div class="i">🛠️</div><h1>지금은 점검 중이에요</h1><p>{$msg}</p>{$untilLine}<p><a href="/">처음 화면으로</a></p></div></body></html>
HTML;
    }
}
