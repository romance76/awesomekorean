<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 관리자 API 권한 등급 한 곳 관리 + 변경 기록(감사 로그).
 *
 * 등급: 1=운영자(moderator) 2=관리자(admin) 3=최고관리자(super_admin)
 * /api/admin/* 의 모든 요청은 이 표의 "필요 등급" 이상일 때만 통과한다.
 * 표에 없는 주소의 기본값은 2(관리자 이상) — 운영자는 아래에 명시한 것만 할 수 있다.
 * (회의 결정: 환불·결제 키·사이트 설정 저장·콘텐츠 삭제·인보이스는 최고관리자 전용)
 *
 * 변경 요청(POST/PUT/PATCH/DELETE)은 admin_audit_log 에 남긴다. 값은 저장하지 않고 "어떤 칸을 보냈는지"만 기록한다
 * (비밀번호·키가 로그에 남지 않도록).
 */
class AdminTier
{
    private const RANK = ['moderator' => 1, 'admin' => 2, 'super_admin' => 3];
    private const LABEL = [1 => '운영자', 2 => '관리자', 3 => '최고관리자'];

    // [허용 메서드(정규식), 경로 정규식('admin/' 뒤), 필요 등급] — 위에서부터 처음 맞는 줄을 쓴다
    private static function rules(): array
    {
        return [
            // ── 운영자(1)도 되는 것: 보기 위주 + 신고 처리 + 글·댓글 숨김 ──
            ['GET', '#^overview$#', 1],
            ['GET', '#^todo-counts$#', 1],
            ['GET', '#^settings/menus$#', 1],
            ['GET', '#^(reports|users|posts|boards)(/|$)#', 1],
            ['GET', '#^board-manager/#', 1],
            ['PUT', '#^reports/\d+$#', 1],
            ['POST', '#^posts/\d+/(hide|pin)$#', 1],
            ['POST', '#^board-manager/[^/]+/(posts|comments)/\d+/toggle$#', 1],

            // ── 최고관리자(3) 전용: 돈·키·설정·삭제·경품 ──
            ['GET', '#^system/sync-all-content/status$#', 2],
            ['ANY', '#^settings(/|$)#', 3],
            ['ANY', '#^(api-keys|firebase|security|accounts-overview|analytics|open-event|todos)(/|$)#', 3],
            ['ANY', '#^system(/|$)#', 3],
            ['POST', '#^payments/\d+/refund$#', 3],
            ['POST', '#^users/\d+/(impersonate|reset-password)$#', 3],
            ['DELETE', '#^users/\d+$#', 3],
            ['ANY', '#^(point-settings|entry-settings|ad-settings|ad-slot-prices|pricing-promotions)(/|$)#', 3],
            ['ANY', '#^(sweepstakes|sweepstakes-.+|prize-claims|entries)(/|$)#', 3],
            ['POST', '#^(fetch-music|fetch-news|fetch-shorts)$#', 3],
            ['ANY', '#^recipes/(clear-all|bulk-delete|sync|sync-all)$#', 3],
            // 삭제는 모두 최고관리자 전용 (운영자·관리자는 숨김·복구만) — 광고·배너·IP 차단 해제는 관리자도 가능
            ['DELETE', '#^(banners|hero-banners|popup-banners|ip-bans)/\d+$#', 2],
            ['DELETE', '#.*#', 3],
        ];
    }

    public static function requiredRank(string $method, string $path): int
    {
        $method = strtoupper($method);
        foreach (self::rules() as [$m, $re, $need]) {
            if (($m === 'ANY' || $m === $method) && preg_match($re, $path)) {
                return $need;
            }
        }
        return 2;
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => '인증이 필요합니다.'], 401);
        }
        $path = ltrim((string) preg_replace('#^api/admin/?#', '', $request->path()), '/');
        $need = self::requiredRank($request->method(), $path);
        $rank = self::RANK[$user->role] ?? 0;

        if ($rank < $need) {
            return response()->json([
                'success' => false,
                'code' => 'role_required',
                'message' => '이 작업은 ' . self::LABEL[$need] . ' 이상만 할 수 있어요.',
                'required_role' => array_search($need, self::RANK, true),
            ], 403);
        }

        $response = $next($request);

        if (!in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $this->audit($request, $user, $path, $response->getStatusCode());
        }
        return $response;
    }

    private function audit(Request $request, $user, string $path, int $status): void
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('admin_audit_log')) return;
            $segs = explode('/', $path);
            $targetId = null;
            foreach (array_reverse($segs) as $s) { if (ctype_digit($s)) { $targetId = (int) $s; break; } }
            // 값은 남기지 않고 보낸 칸 이름만 (비밀값·비밀번호 보호)
            $keys = array_slice(array_keys($request->except(['password', 'current_password', 'api_key', 'secret', 'token'])), 0, 30);
            DB::table('admin_audit_log')->insert([
                'admin_id' => $user->id,
                'action' => mb_substr($request->method() . ' ' . $path, 0, 100),
                'target_type' => mb_substr($segs[0] ?? '', 0, 50) ?: null,
                'target_id' => $targetId,
                'before_value' => null,
                'after_value' => json_encode(['http_status' => $status, 'fields' => $keys], JSON_UNESCAPED_UNICODE),
                'note' => $user->role,
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);   // 기록 실패가 관리 작업을 막지 않게
        }
    }
}
