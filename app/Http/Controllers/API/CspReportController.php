<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * CSP "보고 전용" 모드의 위반 보고를 받는다 (SecurityHeaders 의 Content-Security-Policy-Report-Only 가 보낸다).
 *  - 이 정책은 아무것도 막지 않는다. 어떤 스크립트·이미지·연결이 정책 밖인지 알아보기 위한 기록용이다.
 *  - 같은 종류(위반 항목 + 차단된 주소 + 페이지 경로)는 하루에 한 번만 기록하고, 하루 최대 300종류까지만 남긴다.
 *  - 기록: storage/logs/csp-report.log  (개인정보가 될 수 있는 주소 쿼리·해시는 버리고 호스트·경로만 남긴다)
 */
class CspReportController extends Controller
{
    private const DAILY_LIMIT = 300;

    public function store(Request $request)
    {
        $s = self::summarize(substr((string) $request->getContent(), 0, 8192));
        if ($s) {
            $day = now()->format('Ymd');
            $count = (int) Cache::get("csp:count:$day", 0);
            if ($count < self::DAILY_LIMIT && Cache::add('csp:' . md5($s['directive'] . '|' . $s['blocked'] . '|' . $s['page']), 1, 86400)) {
                Cache::put("csp:count:$day", $count + 1, 172800);
                Log::build(['driver' => 'single', 'path' => storage_path('logs/csp-report.log')])->warning('csp-violation', $s);
            }
        }
        return response('', 204);
    }

    /** 옛 형식({"csp-report":{...}})과 새 Reporting API 형식([{"body":{...}}]) 모두 읽어 핵심만 뽑는다. 읽을 수 없으면 null. */
    public static function summarize(string $raw): ?array
    {
        $j = json_decode($raw, true);
        if (!is_array($j)) return null;
        $r = $j['csp-report'] ?? ($j[0]['body'] ?? ($j['body'] ?? null));
        if (!is_array($r)) return null;

        $directive = (string) ($r['effective-directive'] ?? $r['effectiveDirective'] ?? $r['violated-directive'] ?? $r['violatedDirective'] ?? '');
        $blockedRaw = (string) ($r['blocked-uri'] ?? $r['blockedURL'] ?? $r['blocked-url'] ?? '');
        $doc = (string) ($r['document-uri'] ?? $r['documentURL'] ?? $r['document-url'] ?? '');
        if ($directive === '') return null;

        // 차단된 주소: 주소면 호스트만, inline/eval/data/blob 같은 표시는 그대로
        $blocked = $blockedRaw;
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $blockedRaw)) {
            $blocked = (string) (parse_url($blockedRaw, PHP_URL_HOST) ?: 'unknown');
        } elseif (str_contains($blockedRaw, ':') && !in_array($blockedRaw, ['inline', 'eval', 'data', 'blob'], true)) {
            $blocked = strstr($blockedRaw, ':', true) . ':';
        }
        // 페이지: 경로 앞 두 마디만 (글 번호·검색어 같은 값은 버린다)
        $segs = array_slice(array_values(array_filter(explode('/', (string) parse_url($doc, PHP_URL_PATH)))), 0, 2);
        $page = '/' . implode('/', array_map(fn ($x) => preg_match('/^\d+$/', $x) ? ':id' : mb_substr($x, 0, 40), $segs));

        return [
            'directive' => mb_substr($directive, 0, 60),
            'blocked' => mb_substr($blocked, 0, 120),
            'page' => $page,
            'sample' => mb_substr((string) ($r['script-sample'] ?? $r['sample'] ?? ''), 0, 80),
        ];
    }
}
