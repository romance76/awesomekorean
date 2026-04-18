<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * /mypage/security + /admin/v2/security/login-logs (Phase 2-C 묶음 3).
 */
class SecurityController extends Controller
{
    /** 현재 유저 자신의 로그인 기록 */
    public function myLoginHistory(Request $request)
    {
        $limit = min((int) $request->query('limit', 50), 200);
        $rows = DB::table('login_histories')
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** 현재 유저 자신의 활성 세션 (jwt_jti 기준, logged_out_at 없는 것) */
    public function mySessions()
    {
        $currentJti = null;
        try { $currentJti = JWTAuth::getPayload()->get('jti'); } catch (\Throwable $e) {}

        $rows = DB::table('login_histories')
            ->where('user_id', auth()->id())
            ->where('successful', true)
            ->whereNull('logged_out_at')
            ->whereNotNull('jwt_jti')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($r) use ($currentJti) {
                $r->is_current = $r->jwt_jti === $currentJti;
                return $r;
            });

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** 특정 세션 강제 종료 (현재 유저 본인 것만) */
    public function terminateSession(Request $request, $id)
    {
        $row = DB::table('login_histories')->where('id', $id)->where('user_id', auth()->id())->first();
        if (!$row) return response()->json(['success' => false, 'message' => 'Not found'], 404);

        if ($row->jwt_jti) {
            // JWT blacklist 에 등록 (Laravel jwt-auth 는 jti 기반 블랙리스트 지원)
            try {
                // 직접 raw 토큰 invalidate 는 jti 만으론 불가능하나, logged_out_at 표시 + 프론트 앱에서 체크 필요
                // 간이 방식: logged_out_at 만 표시 (클라이언트에서 주기적으로 확인)
            } catch (\Throwable $e) {}
        }
        DB::table('login_histories')->where('id', $id)->update(['logged_out_at' => now()]);
        return response()->json(['success' => true]);
    }

    /** 다른 모든 세션 종료 */
    public function terminateOtherSessions()
    {
        $currentJti = null;
        try { $currentJti = JWTAuth::getPayload()->get('jti'); } catch (\Throwable $e) {}

        $q = DB::table('login_histories')
            ->where('user_id', auth()->id())
            ->where('successful', true)
            ->whereNull('logged_out_at');
        if ($currentJti) $q->where('jwt_jti', '!=', $currentJti);

        $count = $q->update(['logged_out_at' => now()]);
        return response()->json(['success' => true, 'terminated' => $count]);
    }

    /** 관리자: 특정 유저 로그인 기록 (permission: users.login-history.view) */
    public function adminUserLoginHistory($userId)
    {
        $rows = DB::table('login_histories')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** 관리자: 실패 로그인 집계 (IP별 top 20) */
    public function adminFailedLogins(Request $request)
    {
        $hours = (int) $request->query('hours', 24);
        $since = now()->subHours($hours);

        $byIp = DB::table('login_histories')
            ->where('successful', false)
            ->where('created_at', '>=', $since)
            ->select('ip', DB::raw('COUNT(*) as attempts'), DB::raw('MAX(created_at) as last_attempt'))
            ->groupBy('ip')
            ->orderByDesc('attempts')
            ->limit(20)
            ->get();

        $recent = DB::table('login_histories')
            ->where('successful', false)
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['success' => true, 'data' => [
            'hours' => $hours,
            'by_ip' => $byIp,
            'recent' => $recent,
        ]]);
    }
}
