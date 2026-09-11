<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Report;
use App\Models\User;
use App\Events\NewNotification;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function store(Request $request) {
        $request->validate(['reportable_type'=>'required','reportable_id'=>'required','reason'=>'required']);
        $report = Report::create([
            'reporter_id'=>auth()->id(),
            'reportable_type'=>$request->reportable_type,
            'reportable_id'=>$request->reportable_id,
            'reason'=>$request->reason,
            'content'=>$request->content,
        ]);

        // 관리자에게 실시간 통지가 전혀 없어 대시보드를 수동으로 열어야만
        // 신고 접수를 알 수 있던 문제 수정 — admin/super_admin/moderator 전원에게 알림.
        try {
            $adminIds = User::whereIn('role', ['admin', 'super_admin', 'moderator'])->pluck('id');
            foreach ($adminIds as $adminId) {
                Notification::create([
                    'user_id' => $adminId,
                    'type' => 'report_submitted',
                    'title' => '새 신고가 접수되었습니다',
                    'content' => "사유: {$request->reason}",
                    'data' => ['report_id' => $report->id],
                ]);
                $unread = Notification::where('user_id', $adminId)->whereNull('read_at')->count();
                broadcast(new NewNotification($adminId, $unread, '새 신고가 접수되었습니다'))->toOthers();
            }
        } catch (\Exception $e) {}

        return response()->json(['success'=>true,'data'=>$report],201);
    }
}
