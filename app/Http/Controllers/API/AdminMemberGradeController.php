<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MemberGrade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 회원 등급(레벨 1~15) 직접 지정 — 슈퍼관리자 전용.
 * 등급은 users.lifetime_points(평생 획득 포인트)로 정해지므로, 지정은 이 누적값을 목표 등급 범위 안으로 맞추는 방식이다.
 *  - 목표 등급보다 낮으면 그 등급의 시작점으로 올림, 더 높으면 그 등급의 시작점으로 내림, 이미 범위 안이면 그대로 둔다.
 *  - 쓸 수 있는 포인트 잔액(points)과 포인트 내역은 건드리지 않는다. 변경 전후 값은 감사 로그에 남긴다.
 * 어디서나 같은 등급이 보이도록(목록·채팅·글 작성자 링 등) 별도 "덮어쓰기" 칸 없이 누적 포인트만 맞춘다.
 */
class AdminMemberGradeController extends Controller
{
    /** POST /admin/users/{id}/grade  { level: 1~15, reason?: string } */
    public function set(Request $request, int $id)
    {
        $d = $request->validate([
            'level' => 'required|integer|between:1,15',
            'reason' => 'nullable|string|max:100',
        ], ['level.between' => '등급은 1~15 사이로 골라 주세요.']);

        $tiers = MemberGrade::tiers();
        $level = (int) $d['level'];
        $min = (int) $tiers[$level]['min'];
        $next = isset($tiers[$level + 1]) ? (int) $tiers[$level + 1]['min'] : null;

        $user = User::findOrFail($id);
        $before = (int) $user->lifetime_points;
        $beforeLevel = MemberGrade::levelFor($before);
        $after = $before;
        if ($before < $min || ($next !== null && $before >= $next)) $after = $min;

        if ($after !== $before) DB::table('users')->where('id', $id)->update(['lifetime_points' => $after, 'updated_at' => now()]);

        try {
            if (Schema::hasTable('admin_audit_log')) {
                DB::table('admin_audit_log')->insert([
                    'admin_id' => $request->user()->id, 'action' => 'SET_GRADE users/' . $id, 'target_type' => 'users', 'target_id' => $id,
                    'before_value' => json_encode(['lifetime_points' => $before, 'level' => $beforeLevel]),
                    'after_value' => json_encode(['lifetime_points' => $after, 'level' => $level, 'reason' => $d['reason'] ?? null], JSON_UNESCAPED_UNICODE),
                    'note' => $request->user()->role, 'ip' => $request->ip(), 'created_at' => now(),
                ]);
            }
        } catch (\Throwable $e) { report($e); }

        return response()->json(['success' => true,
            'message' => "등급을 {$tiers[$level]['label']}(Lv.{$level})로 맞췄어요" . ($after !== $before ? " (누적 포인트 {$before} → {$after})" : ' (이미 이 등급이에요)'),
            'data' => ['level' => $level, 'label' => $tiers[$level]['label'], 'lifetime_points' => $after],
        ]);
    }
}
