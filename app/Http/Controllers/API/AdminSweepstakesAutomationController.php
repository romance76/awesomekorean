<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\SweepstakesAutomation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 경품 자동화 (최고관리자 전용)
 * - 반복 일정: 같은 상품·같은 기간 이벤트를 매일/매주/매월, 또는 앞으로 N번 자동 생성 (일시정지·재개·중지)
 * - 가입 보너스: "매 N번째 가입 회원에게 상품" — 당첨자는 지급 관리 화면에 쌓여 쪽지로 상품을 보낸다
 */
class AdminSweepstakesAutomationController extends Controller
{
    private function requireSuperAdmin()
    {
        if (auth()->user()->role !== 'super_admin') {
            abort(response()->json(['success' => false, 'message' => '경품 추첨 관리는 사이트 최고관리자만 접근할 수 있습니다'], 403));
        }
    }

    private function scheduleOut($r): array
    {
        return [
            'id' => $r->id, 'name' => $r->name, 'status' => $r->status,
            'title_template' => $r->title_template, 'description' => $r->description,
            'prize_name' => $r->prize_name, 'prize_value' => $r->prize_value !== null ? (float) $r->prize_value : null,
            'winner_count' => (int) $r->winner_count, 'auto_draw' => (bool) $r->auto_draw,
            'repeat_unit' => $r->repeat_unit, 'weekday' => $r->weekday, 'month_day' => $r->month_day,
            'start_time' => $r->start_time, 'duration_hours' => (int) $r->duration_hours,
            'total_runs' => $r->total_runs, 'runs_done' => (int) $r->runs_done,
            'next_run_at' => $r->next_run_at ? Carbon::parse($r->next_run_at, 'UTC')->toIso8601String() : null,
            'last_run_at' => $r->last_run_at ? Carbon::parse($r->last_run_at, 'UTC')->toIso8601String() : null,
        ];
    }

    private function scheduleRules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'title_template' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'prize_name' => 'required|string|max:255',
            'prize_value' => 'nullable|numeric|min:0|max:100000',
            'winner_count' => 'nullable|integer|min:1|max:10',
            'auto_draw' => 'nullable|boolean',
            'repeat_unit' => 'required|in:daily,weekly,monthly',
            'weekday' => 'nullable|integer|min:0|max:6',
            'month_day' => 'nullable|integer|min:1|max:28',
            'start_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'duration_hours' => 'required|integer|min:1|max:2160',
            'total_runs' => 'nullable|integer|min:1|max:500',
        ];
    }

    // ── 반복 일정 ──
    public function schedules()
    {
        $this->requireSuperAdmin();
        $rows = DB::table('sweepstakes_schedules')->orderByRaw("FIELD(status,'active','paused','completed','stopped')")->orderByDesc('id')->get();
        return response()->json(['success' => true, 'data' => $rows->map(fn ($r) => $this->scheduleOut($r))->values()]);
    }

    public function storeSchedule(Request $request)
    {
        $this->requireSuperAdmin();
        $d = $request->validate($this->scheduleRules());
        $d['winner_count'] = (int) ($d['winner_count'] ?? 1);
        $d['auto_draw'] = (bool) ($d['auto_draw'] ?? false);
        $row = (object) array_merge($d, ['weekday' => $d['weekday'] ?? 1, 'month_day' => $d['month_day'] ?? 1]);
        $first = $request->filled('first_start_at')
            ? Carbon::parse($request->input('first_start_at'), 'America/New_York')->utc()   // 애틀랜타 시각으로 입력한 첫 시작
            : SweepstakesAutomation::nextRunAfter($row);
        $id = DB::table('sweepstakes_schedules')->insertGetId(array_merge($d, [
            'weekday' => $row->weekday, 'month_day' => $row->month_day,
            'status' => 'paused', 'runs_done' => 0, 'next_run_at' => $first->toDateTimeString(),
            'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
        ]));
        return response()->json(['success' => true, 'data' => $this->scheduleOut(DB::table('sweepstakes_schedules')->find($id))], 201);
    }

    public function updateSchedule(Request $request, $id)
    {
        $this->requireSuperAdmin();
        $cur = DB::table('sweepstakes_schedules')->where('id', $id)->first();
        abort_unless($cur, 404);
        $d = $request->validate($this->scheduleRules());
        $d['winner_count'] = (int) ($d['winner_count'] ?? 1);
        $d['auto_draw'] = (bool) ($d['auto_draw'] ?? false);
        $upd = array_merge($d, ['updated_at' => now()]);
        if ($request->filled('first_start_at') && (int) $cur->runs_done === 0) {
            $upd['next_run_at'] = Carbon::parse($request->input('first_start_at'), 'America/New_York')->utc()->toDateTimeString();
        }
        DB::table('sweepstakes_schedules')->where('id', $id)->update($upd);
        return response()->json(['success' => true, 'data' => $this->scheduleOut(DB::table('sweepstakes_schedules')->find($id))]);
    }

    /** action: start | pause | stop */
    public function scheduleAction(Request $request, $id)
    {
        $this->requireSuperAdmin();
        $request->validate(['action' => 'required|in:start,pause,stop,run_now']);
        $row = DB::table('sweepstakes_schedules')->where('id', $id)->first();
        abort_unless($row, 404);
        $a = $request->input('action');
        if ($a === 'stop') {
            DB::table('sweepstakes_schedules')->where('id', $id)->update(['status' => 'stopped', 'updated_at' => now()]);
        } elseif ($a === 'pause') {
            DB::table('sweepstakes_schedules')->where('id', $id)->update(['status' => 'paused', 'updated_at' => now()]);
        } elseif ($a === 'start') {
            if ($row->status === 'completed') {
                return response()->json(['success' => false, 'message' => '정해 둔 횟수를 모두 마친 일정이에요. 새 일정을 만들어 주세요'], 422);
            }
            // 켤 때 다음 시각이 이미 지났으면 규칙에 맞는 다음 시각으로 옮긴다 (밀린 회차를 몰아서 만들지 않음)
            $next = $row->next_run_at ? Carbon::parse($row->next_run_at, 'UTC') : null;
            if (!$next || $next->lt(now())) $next = SweepstakesAutomation::nextRunAfter($row);
            DB::table('sweepstakes_schedules')->where('id', $id)->update(['status' => 'active', 'next_run_at' => $next->toDateTimeString(), 'updated_at' => now()]);
        } elseif ($a === 'run_now') {
            if ($row->status === 'completed' || $row->status === 'stopped') {
                return response()->json(['success' => false, 'message' => '중지·완료된 일정이에요'], 422);
            }
            try {
                $sw = SweepstakesAutomation::createRun((int) $id, now());
            } catch (\Throwable $e) {
                report($e);
                return response()->json(['success' => false, 'message' => '이벤트를 만들지 못했어요: ' . $e->getMessage()], 500);
            }
            if (!$sw) return response()->json(['success' => false, 'message' => '더 만들 회차가 없어요'], 422);
        }
        return response()->json(['success' => true, 'data' => $this->scheduleOut(DB::table('sweepstakes_schedules')->find($id))]);
    }

    // ── 가입 보너스 ──
    private function milestoneOut($r): array
    {
        return [
            'id' => $r->id, 'name' => $r->name, 'status' => $r->status, 'trigger_type' => $r->trigger_type,
            'every_n' => (int) $r->every_n, 'prize_name' => $r->prize_name,
            'prize_value' => $r->prize_value !== null ? (float) $r->prize_value : null, 'prize_type' => $r->prize_type,
            'max_awards' => $r->max_awards, 'awards_done' => (int) $r->awards_done, 'container_id' => $r->container_id,
        ];
    }

    private function milestoneRules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'every_n' => 'required|integer|min:2|max:100000',
            'prize_name' => 'required|string|max:255',
            'prize_value' => 'nullable|numeric|min:0|max:100000',
            'prize_type' => 'required|in:digital,physical',
            'max_awards' => 'nullable|integer|min:1|max:100000',
        ];
    }

    public function milestones()
    {
        $this->requireSuperAdmin();
        $rows = DB::table('sweepstakes_milestones')->orderByRaw("FIELD(status,'active','paused','stopped')")->orderByDesc('id')->get();
        $members = (int) DB::table('users')->count();
        return response()->json(['success' => true, 'member_count' => $members, 'data' => $rows->map(fn ($r) => $this->milestoneOut($r))->values()]);
    }

    public function storeMilestone(Request $request)
    {
        $this->requireSuperAdmin();
        $d = $request->validate($this->milestoneRules());
        $id = DB::table('sweepstakes_milestones')->insertGetId(array_merge($d, [
            'trigger_type' => 'nth_signup', 'status' => 'paused', 'awards_done' => 0,
            'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
        ]));
        return response()->json(['success' => true, 'data' => $this->milestoneOut(DB::table('sweepstakes_milestones')->find($id))], 201);
    }

    public function updateMilestone(Request $request, $id)
    {
        $this->requireSuperAdmin();
        abort_unless(DB::table('sweepstakes_milestones')->where('id', $id)->exists(), 404);
        $d = $request->validate($this->milestoneRules());
        DB::table('sweepstakes_milestones')->where('id', $id)->update(array_merge($d, ['updated_at' => now()]));
        return response()->json(['success' => true, 'data' => $this->milestoneOut(DB::table('sweepstakes_milestones')->find($id))]);
    }

    public function milestoneAction(Request $request, $id)
    {
        $this->requireSuperAdmin();
        $request->validate(['action' => 'required|in:start,pause,stop']);
        abort_unless(DB::table('sweepstakes_milestones')->where('id', $id)->exists(), 404);
        $st = ['start' => 'active', 'pause' => 'paused', 'stop' => 'stopped'][$request->input('action')];
        $upd = ['status' => $st, 'updated_at' => now()];
        $cur = DB::table('sweepstakes_milestones')->where('id', $id)->first();
        // 처음 켤 때 현재 회원 수를 기준으로 삼는다 — 이 뒤로 가입하는 사람부터 센다
        if ($st === 'active' && (int) $cur->awards_done === 0 && $cur->status !== 'active') {
            $upd['base_count'] = (int) DB::table('users')->count();
        }
        DB::table('sweepstakes_milestones')->where('id', $id)->update($upd);
        return response()->json(['success' => true, 'data' => $this->milestoneOut(DB::table('sweepstakes_milestones')->find($id))]);
    }
}
