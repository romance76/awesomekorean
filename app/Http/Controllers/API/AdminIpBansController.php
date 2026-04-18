<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * IP 차단 관리 (Phase 2-C Post - 단일 IP + CIDR 통합).
 */
class AdminIpBansController extends Controller
{
    public function index(Request $request)
    {
        $q = DB::table('ip_bans')
            ->leftJoin('users', 'users.id', '=', 'ip_bans.banned_by')
            ->select('ip_bans.*', 'users.nickname as banned_by_name');

        if ($request->filled('type')) {
            $q->where('is_cidr', $request->type === 'cidr');
        }
        if ($request->filled('search')) {
            $s = $request->query('search');
            $q->where(function ($w) use ($s) {
                $w->where('ip_address', 'like', "%{$s}%")->orWhere('reason', 'like', "%{$s}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $q->orderByDesc('ip_bans.created_at')->limit(500)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'ip_address' => 'required|string|max:50',
            'is_cidr'    => 'boolean',
            'reason'     => 'nullable|string|max:500',
            'expires_at' => 'nullable|date',
        ]);

        // CIDR 유효성 검증
        if (!empty($data['is_cidr'])) {
            if (!str_contains($data['ip_address'], '/')) {
                return response()->json(['success' => false, 'message' => 'CIDR 은 "/" 필수 (예: 1.2.3.0/24)'], 422);
            }
            [$subnet, $mask] = explode('/', $data['ip_address']);
            if (!filter_var($subnet, FILTER_VALIDATE_IP) || (int)$mask < 0 || (int)$mask > 32) {
                return response()->json(['success' => false, 'message' => '올바르지 않은 CIDR'], 422);
            }
        } else {
            if (!filter_var($data['ip_address'], FILTER_VALIDATE_IP)) {
                return response()->json(['success' => false, 'message' => '올바르지 않은 IP'], 422);
            }
        }

        // 중복 방지
        $existing = DB::table('ip_bans')
            ->where('ip_address', $data['ip_address'])
            ->where(function ($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); })
            ->exists();
        if ($existing) {
            return response()->json(['success' => false, 'message' => '이미 차단된 IP/대역'], 422);
        }

        $id = DB::table('ip_bans')->insertGetId([
            'ip_address' => $data['ip_address'],
            'is_cidr'    => $data['is_cidr'] ?? false,
            'reason'     => $data['reason'] ?? '관리자 수동 차단',
            'banned_by'  => auth()->id(),
            'expires_at' => $data['expires_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // IP 캐시 무효화
        Cache::forget("ip_ban:{$data['ip_address']}");

        DB::table('admin_audit_log')->insert([
            'admin_id'    => auth()->id(),
            'action'      => 'ip_ban_create',
            'target_type' => 'ip_ban',
            'target_id'   => $id,
            'after_value' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'ip'          => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json(['success' => true, 'id' => $id]);
    }

    public function destroy($id, Request $request)
    {
        $row = DB::table('ip_bans')->where('id', $id)->first();
        if (!$row) return response()->json(['success' => false, 'message' => 'Not found'], 404);

        DB::table('ip_bans')->where('id', $id)->delete();
        Cache::forget("ip_ban:{$row->ip_address}");

        DB::table('admin_audit_log')->insert([
            'admin_id'    => auth()->id(),
            'action'      => 'ip_ban_delete',
            'target_type' => 'ip_ban',
            'target_id'   => $id,
            'before_value'=> json_encode(['ip_address' => $row->ip_address, 'is_cidr' => $row->is_cidr ?? false], JSON_UNESCAPED_UNICODE),
            'ip'          => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        $count = DB::table('ip_bans')->whereIn('id', $ids)->delete();
        return response()->json(['success' => true, 'deleted' => $count]);
    }
}
