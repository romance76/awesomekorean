<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 관리자 감사 로그 조회 (읽기 전용, 최고관리자 전용 — AdminTier 규칙 'audit-log').
 * admin_audit_log 는 AdminTier 가 변경 요청마다 남긴다. 값은 저장하지 않고 "보낸 칸 이름"만 있다.
 */
class AdminAuditLogController extends Controller
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('admin_audit_log')) {
            return response()->json(['success' => true, 'data' => ['data' => [], 'total' => 0, 'last_page' => 1], 'targets' => []]);
        }

        $request->validate([
            'q' => 'nullable|string|max:100',
            'target' => 'nullable|string|max:50',
            'days' => 'nullable|integer|min:0|max:3650',
            'admin_id' => 'nullable|integer|min:1',
            'failed' => 'nullable|boolean',
        ]);

        $q = DB::table('admin_audit_log as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.admin_id')
            ->select('l.id', 'l.admin_id', 'l.action', 'l.target_type', 'l.target_id', 'l.after_value', 'l.note', 'l.ip', 'l.created_at', 'u.name as admin_name', 'u.email as admin_email');

        if ($request->filled('target')) $q->where('l.target_type', $request->input('target'));
        if ($request->filled('admin_id')) $q->where('l.admin_id', (int) $request->input('admin_id'));
        if ($request->filled('days') && (int) $request->input('days') > 0) $q->where('l.created_at', '>=', now()->subDays((int) $request->input('days')));
        if ($request->boolean('failed')) $q->where(fn ($w) => $w->where('l.after_value', 'like', '%"http_status":4%')->orWhere('l.after_value', 'like', '%"http_status":5%'));
        if ($request->filled('q')) {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $request->input('q')) . '%';
            $q->where(fn ($w) => $w->where('l.action', 'like', $term)->orWhere('u.name', 'like', $term)->orWhere('u.email', 'like', $term)->orWhere('l.ip', 'like', $term));
        }

        $page = $q->orderByDesc('l.id')->paginate(30);
        $page->getCollection()->transform(function ($r) {
            $extra = json_decode((string) $r->after_value, true);
            $r->http_status = is_array($extra) ? ($extra['http_status'] ?? null) : null;
            $r->fields = is_array($extra) ? ($extra['fields'] ?? []) : [];
            $r->reason = is_array($extra) ? ($extra['reason'] ?? null) : null;   // 환불 같은 일부 동작은 사유를 함께 기록
            unset($r->after_value);
            return $r;
        });

        $targets = DB::table('admin_audit_log')->whereNotNull('target_type')->distinct()->orderBy('target_type')->limit(60)->pluck('target_type');

        return response()->json(['success' => true, 'data' => $page, 'targets' => $targets]);
    }
}
