<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\{ElderSetting, ElderCheckinLog, ElderSosLog};
use Illuminate\Http\Request;

class ElderController extends Controller
{
    public function settings() {
        $s = ElderSetting::firstOrCreate(['user_id'=>auth()->id()], ['checkin_interval'=>24]);
        return response()->json(['success'=>true,'data'=>$s]);
    }

    public function updateSettings(Request $request) {
        $s = ElderSetting::updateOrCreate(['user_id'=>auth()->id()], $request->only('guardian_id','checkin_interval','sos_contacts','medications','health_notes'));
        return response()->json(['success'=>true,'data'=>$s]);
    }

    public function checkin(Request $request) {
        $log = ElderCheckinLog::create(['user_id'=>auth()->id(),'checked_in_at'=>now(),'lat'=>$request->lat,'lng'=>$request->lng,'status'=>'ok']);
        // 체크인 포인트 +5
        auth()->user()->addPoints(5, '안심서비스 체크인');
        return response()->json(['success'=>true,'data'=>$log]);
    }

    public function sos(Request $request) {
        $log = ElderSosLog::create(['user_id'=>auth()->id(),'lat'=>$request->lat,'lng'=>$request->lng,'message'=>$request->message]);
        return response()->json(['success'=>true,'data'=>$log]);
    }

    public function checkinHistory() {
        $logs = ElderCheckinLog::where('user_id',auth()->id())->orderByDesc('checked_in_at')->paginate(20);
        return response()->json(['success'=>true,'data'=>$logs]);
    }

    public function guardianWards() {
        $wards = ElderSetting::with('user:id,name,nickname,avatar')->where('guardian_id',auth()->id())->get();
        return response()->json(['success'=>true,'data'=>$wards]);
    }

    // ─── 안심서비스 v2 ───

    /** 보호대상 이메일 검색 */
    public function searchWard(Request $request) {
        $request->validate(['email' => 'required|email']);
        $user = \App\Models\User::where('email', $request->email)->first();
        if (!$user) return response()->json(['ok' => false, 'message' => '등록되지 않은 이메일입니다.']);
        if ($user->id === auth()->id()) return response()->json(['ok' => false, 'message' => '본인은 등록할 수 없습니다.']);
        if (!$user->allow_elder_service) return response()->json(['ok' => false, 'message' => '해당 사용자가 안심 서비스를 원하지 않습니다.']);

        // 이미 등록 확인
        $exists = \DB::table('elder_guardians')->where('guardian_user_id', auth()->id())->where('ward_user_id', $user->id)->exists();
        if ($exists) return response()->json(['ok' => false, 'message' => '이미 등록된 보호대상입니다.']);

        return response()->json(['ok' => true, 'message' => '안심 서비스 등록이 가능합니다.', 'user' => [
            'id' => $user->id, 'name' => $user->name, 'city' => $user->city, 'state' => $user->state,
        ]]);
    }

    /** 보호대상 등록 */
    public function registerWard(Request $request) {
        $request->validate(['ward_user_id' => 'required|exists:users,id']);
        $ward = \App\Models\User::findOrFail($request->ward_user_id);
        if (!$ward->allow_elder_service) return response()->json(['success' => false, 'message' => '대상이 안심 서비스를 거절했습니다.'], 403);

        \DB::table('elder_guardians')->updateOrInsert(
            ['guardian_user_id' => auth()->id(), 'ward_user_id' => $request->ward_user_id],
            ['status' => 'active', 'updated_at' => now(), 'created_at' => now()]
        );
        return response()->json(['success' => true, 'message' => '등록되었습니다.']);
    }

    /** 내 보호대상 목록 */
    public function myWards() {
        $wards = \DB::table('elder_guardians')
            ->where('guardian_user_id', auth()->id())
            ->get();

        $result = $wards->map(function($w) {
            $user = \App\Models\User::select('id','name','email','city','state','phone')->find($w->ward_user_id);
            $schedule = \DB::table('elder_schedules')->where('elder_guardian_id', $w->id)->first();
            return [
                'id' => $w->id,
                'ward' => $user,
                'status' => $w->status,
                'schedule' => $schedule ? [
                    'type' => $schedule->type,
                    'time_start' => $schedule->time_start,
                    'time_end' => $schedule->time_end,
                    'calls_per_day' => $schedule->calls_per_day,
                    'days' => json_decode($schedule->days, true),
                    'scheduled_times' => json_decode($schedule->scheduled_times, true),
                ] : null,
            ];
        });

        return response()->json(['success' => true, 'data' => $result]);
    }

    /** 스케줄 저장 */
    public function saveSchedule(Request $request) {
        $request->validate(['elder_guardian_id' => 'required|integer', 'type' => 'required|in:random,scheduled']);

        // 보호자 확인
        $guardian = \DB::table('elder_guardians')->where('id', $request->elder_guardian_id)->where('guardian_user_id', auth()->id())->first();
        if (!$guardian) return response()->json(['success' => false, 'message' => '권한이 없습니다.'], 403);

        \DB::table('elder_schedules')->updateOrInsert(
            ['elder_guardian_id' => $request->elder_guardian_id],
            [
                'type' => $request->type,
                'time_start' => $request->time_start ?? '09:00',
                'time_end' => $request->time_end ?? '18:00',
                'calls_per_day' => $request->calls_per_day ?? 1,
                'days' => json_encode($request->days ?? []),
                'scheduled_times' => json_encode($request->scheduled_times ?? []),
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['success' => true, 'message' => '스케줄이 저장되었습니다.']);
    }
}
