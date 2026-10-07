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

        $this->autoHideReviewIfNeeded($report);

        return response()->json(['success'=>true,'data'=>$report],201);
    }

    /**
     * 내돈내산 리뷰는 서로 다른 회원이 N명(설정, 기본 3) 신고하면 임시로 내려가 관리자 확인을 기다린다.
     * 같은 사람이 여러 번 신고해도 한 명으로 센다.
     */
    private function autoHideReviewIfNeeded(Report $report): void
    {
        if ($report->reportable_type !== \App\Models\AmazonProduct::class) return;
        $limit = \App\Support\PointRules::get('shopping_review_autohide_reports', 3);
        $p = \App\Models\AmazonProduct::find($report->reportable_id);
        if ($limit <= 0 || !$p || !$p->user_id || $p->status !== 'published') return;

        $n = Report::where('reportable_type', \App\Models\AmazonProduct::class)->where('reportable_id', $p->id)->where('reporter_id', '!=', $p->user_id)->distinct()->count('reporter_id');
        if ($n < $limit) return;

        $p->update(['status' => 'hidden', 'admin_note' => "신고 {$n}명 접수로 자동 내림 — 관리자 확인 대기"]);
        \App\Support\ShoppingReviews::forget();
        try {
            Notification::create(['user_id' => $p->user_id, 'type' => 'shopping_review', 'title' => '내돈내산 리뷰가 내려갔어요', 'content' => "'{$p->title}' 리뷰에 신고가 여러 건 접수되어 임시로 내려갔어요. 관리자가 확인한 뒤 다시 공개할지 결정해요.", 'data' => ['product_id' => $p->id]]);
            foreach (User::whereIn('role', ['admin', 'super_admin', 'moderator'])->pluck('id') as $adminId) {
                Notification::create(['user_id' => $adminId, 'type' => 'shopping_review', 'title' => '신고 누적으로 리뷰가 내려갔어요', 'content' => "'{$p->title}' — 신고 {$n}명. 확인 후 복구하거나 삭제해주세요.", 'data' => ['product_id' => $p->id]]);
            }
        } catch (\Throwable $e) {}
    }
}
