<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireReauth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * 관리자 비밀번호 재확인: 맞으면 10분 동안 위험한 동작을 허용한다.
 * 비밀번호는 저장·기록하지 않고, 성공/실패 사실만 감사 로그에 남긴다.
 */
class AdminReauthController extends Controller
{
    public function confirm(Request $request)
    {
        $request->validate(['password' => 'required|string|max:200']);
        $user = $request->user();
        $ok = $user && Hash::check($request->password, $user->password);
        try {
            DB::table('admin_audit_log')->insert([
                'admin_id' => $user->id, 'action' => $ok ? 'REAUTH ok' : 'REAUTH fail', 'target_type' => 'auth',
                'note' => $user->role, 'ip' => $request->ip(), 'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
        if (!$ok) {
            return response()->json(['success' => false, 'message' => '비밀번호가 맞지 않아요.'], 422);
        }
        Cache::put(RequireReauth::key($user->id), 1, RequireReauth::TTL_SECONDS);
        return response()->json(['success' => true, 'valid_for_seconds' => RequireReauth::TTL_SECONDS]);
    }

    /** 현재 재확인 상태 (화면에서 "n분 남음" 표시용) */
    public function status(Request $request)
    {
        return response()->json(['success' => true, 'active' => Cache::has(RequireReauth::key($request->user()->id))]);
    }
}
