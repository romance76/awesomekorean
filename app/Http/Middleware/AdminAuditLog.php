<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 2-C Post: 관리자 행동 자동 감사 로그.
 * 쓰기 메서드(POST/PUT/PATCH/DELETE) 만 기록.
 * admin_audit_log 테이블에 자동 저장 + /admin/v2/security/audit 에서 조회.
 */
class AdminAuditLog
{
    // 민감 정보 마스킹 대상
    protected array $sensitive = ['password', 'password_confirmation', 'current_password', 'new_password', 'api_key', 'token', 'secret'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 쓰기 메서드 + 관리자만 기록
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) return $response;
        $user = auth()->user();
        if (!$user) return $response;

        try {
            $payload = $this->maskSensitive($request->except(['password', 'password_confirmation']));
            $statusCode = $response->getStatusCode();

            DB::table('admin_audit_log')->insert([
                'admin_id'     => $user->id,
                'action'       => sprintf('%s %s', $request->method(), $request->path()),
                'target_type'  => $this->detectTargetType($request->path()),
                'target_id'    => $this->detectTargetId($request),
                'before_value' => null,  // 상세 before/after 는 컨트롤러가 명시적으로 보충 권장
                'after_value'  => json_encode([
                    'status'  => $statusCode,
                    'payload' => $payload,
                    'query'   => $request->query(),
                ], JSON_UNESCAPED_UNICODE),
                'ip'           => $request->ip(),
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AdminAuditLog insert failed: ' . $e->getMessage());
        }

        return $response;
    }

    protected function maskSensitive(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = $this->maskSensitive($v);
            } elseif (is_string($k) && in_array(strtolower($k), $this->sensitive, true)) {
                $data[$k] = '***REDACTED***';
            }
        }
        return $data;
    }

    protected function detectTargetType(string $path): ?string
    {
        // /api/admin/users/123 → 'user'
        // /api/admin/posts/45/hide → 'post'
        // /api/admin/api-keys → 'api_key'
        if (preg_match('#/admin/([a-z-]+?)s?(?:/|$)#', $path, $m)) {
            return str_replace('-', '_', $m[1]);
        }
        return null;
    }

    protected function detectTargetId(Request $request): ?int
    {
        foreach ($request->route()->parameters() ?? [] as $k => $v) {
            if (is_numeric($v)) return (int) $v;
        }
        return null;
    }
}
