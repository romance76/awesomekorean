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
        $request->validate([
            'reportable_type' => 'required|string|max:100',
            'reportable_id' => 'required|integer|min:1',
            'reason' => 'required|string|max:200',
            'content' => 'nullable|string|max:2000',
        ], [
            'reportable_type.required' => '신고할 대상을 알 수 없어요.',
            'reportable_id.required' => '신고할 대상을 알 수 없어요.',
            'reportable_id.integer' => '신고할 대상을 알 수 없어요.',
            'reason.required' => '신고 사유를 골라 주세요.',
            'reason.max' => '신고 사유는 200자까지 쓸 수 있어요.',
            'content.max' => '자세한 내용은 2000자까지 쓸 수 있어요.',
        ]);
        $class = \App\Support\ReportTargets::resolve($request->reportable_type);
        if (!$class) {
            return response()->json(['success' => false, 'message' => '신고할 수 없는 종류예요.'], 422);
        }
        $target = $class::find((int) $request->reportable_id);
        if (!$target) {
            return response()->json(['success' => false, 'message' => '이미 삭제되었거나 없는 대상이에요.'], 404);
        }
        if (\App\Support\ReportTargets::ownerId($target) === (int) auth()->id()) {
            return response()->json(['success' => false, 'message' => '본인 글이나 본인 계정은 신고할 수 없어요.'], 422);
        }
        // 같은 대상을 처리 전에 또 신고하면 한 건으로 (알림·자동 숨김이 반복되지 않게)
        $dup = Report::where('reporter_id', auth()->id())->where('reportable_type', $class)
            ->where('reportable_id', $target->getKey())->where('status', 'pending')->exists();
        if ($dup) {
            return response()->json(['success' => false, 'message' => '이미 신고하셨어요. 관리자가 확인 중이에요.'], 409);
        }

        $report = Report::create([
            'reporter_id'=>auth()->id(),
            'reportable_type'=>$class,
            'reportable_id'=>$target->getKey(),
            'reason'=>$request->reason,
            'content'=>$request->content,
        ]);
        // 처리 이력의 첫 줄 — 신고 접수 (누가 언제 접수했는지)
        $report->addLog('created', null, 'pending', null, $request->reason, optional(auth()->user())->nickname ?: optional(auth()->user())->name);

        // 관리자에게 실시간 통지가 전혀 없어 대시보드를 수동으로 열어야만
        // 신고 접수를 알 수 있던 문제 수정 — admin/super_admin/moderator 전원에게 알림.
        try {
            $adminIds = User::whereIn('role', ['admin', 'super_admin', 'moderator'])->pluck('id');
            foreach ($adminIds as $adminId) {
                Notification::create([
                    'user_id' => $adminId,
                    'type' => 'report_submitted',
                    'title' => '새 신고가 접수되었습니다',
                    'content' => '사유: ' . mb_substr($request->reason, 0, 100),
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
