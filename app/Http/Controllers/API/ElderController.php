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
}
