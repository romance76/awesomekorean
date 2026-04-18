<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 관리자 유저 운영 작업 (Phase 2-C Post).
 * - 강제 비번 리셋 (임시 비번 생성·반환)
 * - 포인트 대량 지급/회수 (Kay 요청: 포인트 회수 기능)
 */
class AdminUserOpsController extends Controller
{
    /**
     * 특정 유저 비밀번호 강제 리셋.
     * 관리자가 보안 사고 대응 시 사용. 임시 비번을 응답으로 반환 (1회만 노출).
     */
    public function forcePasswordReset(Request $request, $userId)
    {
        $user = User::find($userId);
        if (!$user) return response()->json(['success' => false, 'message' => 'Not found'], 404);

        // 임시 비번 생성 (12자, 영숫자+특수)
        $temp = Str::password(12, true, true, true, false);
        $user->forceFill(['password' => Hash::make($temp)])->save();

        // 감사: 이 유저의 모든 활성 세션 강제 종료
        DB::table('login_histories')
            ->where('user_id', $user->id)
            ->where('successful', true)
            ->whereNull('logged_out_at')
            ->update(['logged_out_at' => now()]);

        // 명시적 audit 기록
        DB::table('admin_audit_log')->insert([
            'admin_id'    => auth()->id(),
            'action'      => 'force_password_reset',
            'target_type' => 'user',
            'target_id'   => $user->id,
            'note'        => 'Forced password reset by admin',
            'ip'          => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '비밀번호가 리셋되고 모든 세션이 종료되었습니다',
            'data' => [
                'temp_password' => $temp,
                'note' => '이 비밀번호는 한 번만 표시됩니다. 유저에게 안전한 경로로 전달 후 재설정을 유도하세요.',
            ],
        ]);
    }

    /**
     * 포인트 대량 지급.
     * 여러 유저에게 동시 지급 (이벤트 보상·보상 지급 등).
     */
    public function bulkGrantPoints(Request $request)
    {
        $data = $request->validate([
            'user_ids'  => 'required|array|min:1|max:500',
            'user_ids.*'=> 'integer|exists:users,id',
            'amount'    => 'required|integer|min:1|max:100000',
            'reason'    => 'required|string|max:255',
        ]);

        $count = 0;
        foreach ($data['user_ids'] as $uid) {
            $user = User::find($uid);
            if (!$user) continue;
            // addPoints($amount, $reason, $type): type 은 point_histories.type 구분
            $user->addPoints($data['amount'], $data['reason'], 'admin_grant');
            $count++;
        }

        DB::table('admin_audit_log')->insert([
            'admin_id'    => auth()->id(),
            'action'      => 'bulk_grant_points',
            'target_type' => 'user_batch',
            'after_value' => json_encode([
                'user_ids' => $data['user_ids'],
                'amount'   => $data['amount'],
                'reason'   => $data['reason'],
                'count'    => $count,
            ], JSON_UNESCAPED_UNICODE),
            'ip'          => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json(['success' => true, 'granted_count' => $count, 'total_points' => $count * $data['amount']]);
    }

    /**
     * 포인트 회수 (Kay 최우선 요청 기능).
     * 단일 유저에게서 지정 포인트 회수. 잔액 이하로 설정 불가 (잔액까지만 차감).
     */
    public function revokePoints(Request $request, $userId)
    {
        $data = $request->validate([
            'amount' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
            'force'  => 'boolean',  // true 면 잔액보다 많아도 0 까지 차감
        ]);

        $user = User::find($userId);
        if (!$user) return response()->json(['success' => false, 'message' => 'Not found'], 404);

        $before = (int) $user->points;
        $revokeAmount = min($data['amount'], $before);  // 잔액 이상 차감 안 함
        if (!empty($data['force']) && $data['amount'] > $before) {
            // force 면 잔액까지만 차감 + 기록
            $revokeAmount = $before;
        }

        if ($revokeAmount === 0) {
            return response()->json(['success' => false, 'message' => '회수할 포인트가 없습니다 (잔액 0)'], 422);
        }

        $user->addPoints(-$revokeAmount, $data['reason'], 'admin_revoke');

        DB::table('admin_audit_log')->insert([
            'admin_id'    => auth()->id(),
            'action'      => 'revoke_points',
            'target_type' => 'user',
            'target_id'   => $user->id,
            'before_value'=> json_encode(['points' => $before], JSON_UNESCAPED_UNICODE),
            'after_value' => json_encode([
                'points_after' => $before - $revokeAmount,
                'revoked'      => $revokeAmount,
                'reason'       => $data['reason'],
            ], JSON_UNESCAPED_UNICODE),
            'ip'          => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json([
            'success' => true,
            'revoked' => $revokeAmount,
            'before'  => $before,
            'after'   => $before - $revokeAmount,
        ]);
    }

    /**
     * 유저 포인트 이력 (관리자 조회용 — Members 페이지 기존 기능 보완).
     */
    public function userPointHistory($userId)
    {
        $rows = DB::table('point_histories')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
        return response()->json(['success' => true, 'data' => $rows]);
    }
}
