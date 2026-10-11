<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\EmergencySwitches;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 비상 스위치 목록 보기 / 켜기·끄기 — 최고관리자 전용 (AdminTier 규칙표: emergency-switches → 3).
 * 바꿀 때마다 admin_audit_log 에 "누가·언제·무엇을·켬/끔" 을 남긴다 (AdminTier 는 보낸 칸 이름만 남기므로 여기서 변경 전·후를 따로 기록).
 */
class AdminEmergencySwitchController extends Controller
{
    public function index()
    {
        $items = [];
        foreach (EmergencySwitches::SWITCHES as $name => $def) {
            $items[] = $this->present($name, $def);
        }
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function update(Request $request, string $name)
    {
        if (!isset(EmergencySwitches::SWITCHES[$name])) {
            return response()->json(['success' => false, 'message' => '알 수 없는 스위치예요.'], 404);
        }
        $request->validate(['paused' => 'required|boolean']);
        $paused = $request->boolean('paused');

        try {
            $before = EmergencySwitches::set($name, $paused);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => '스위치를 바꾸지 못했어요. 잠시 후 다시 시도해 주세요.'], 500);
        }

        $this->audit($request, $name, $before, $paused);

        $def = EmergencySwitches::SWITCHES[$name];
        return response()->json([
            'success' => true,
            'message' => $paused ? "{$def['label']}을(를) 멈췄어요." : "{$def['label']}을(를) 다시 시작했어요.",
            'data' => $this->present($name, $def),
        ]);
    }

    private function present(string $name, array $def): array
    {
        $paused = EmergencySwitches::on($name);
        $changedAt = null;
        $changedBy = null;
        try {
            $changedAt = DB::table('site_settings')->where('key', $def['setting'])->value('updated_at');
            if (Schema::hasTable('admin_audit_log')) {
                $row = DB::table('admin_audit_log')->where('action', 'EMERGENCY ' . $name)->orderByDesc('id')->first(['admin_id']);
                if ($row && $row->admin_id) {
                    $u = DB::table('users')->where('id', $row->admin_id)->first(['name', 'nickname']);
                    $changedBy = $u ? ($u->nickname ?: $u->name) : null;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
        return [
            'key' => $name,
            'label' => $def['label'],
            'icon' => $def['icon'],
            'paused' => $paused,
            'description' => $paused ? $def['when_paused'] : $def['when_running'],
            'confirm' => $paused ? $def['confirm_resume'] : $def['confirm_pause'],
            // 마지막으로 바꾼 시각(UTC ISO) — 화면에서 애틀랜타 시각과 UTC 를 함께 보여 줌
            'changed_at' => $changedAt ? \Carbon\Carbon::parse($changedAt, 'UTC')->toIso8601String() : null,
            'changed_by' => $changedBy,
        ];
    }

    private function audit(Request $request, string $name, bool $before, bool $after): void
    {
        try {
            if (!Schema::hasTable('admin_audit_log')) return;
            DB::table('admin_audit_log')->insert([
                'admin_id' => $request->user()->id,
                'action' => mb_substr('EMERGENCY ' . $name, 0, 100),
                'target_type' => 'emergency_switch',
                'target_id' => null,
                'before_value' => $before ? 'paused' : 'running',
                'after_value' => $after ? 'paused' : 'running',
                'note' => $request->user()->role,
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);   // 기록 실패가 스위치 동작을 막지 않게
        }
    }
}
