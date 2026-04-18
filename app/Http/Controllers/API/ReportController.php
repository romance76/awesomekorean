<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Report;
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

        // Phase 2-C Post: 실시간 관리자 알림 브로드캐스트
        try {
            $type = strtolower(class_basename($report->reportable_type));
            event(new \App\Events\AdminAlert(
                'report',
                "🚨 새 신고 ({$type})",
                substr($report->reason ?? '', 0, 100),
                '/admin/v2/security/reports',
                'warning'
            ));
        } catch (\Throwable $e) {}

        return response()->json(['success'=>true,'data'=>$report],201);
    }
}
