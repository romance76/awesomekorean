<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * POST /api/reports
     * Create a report. Auto-hide at 3+ reports, admin notification at 5+.
     */
    public function store(Request $request)
    {
        $request->validate([
            'reportable_type' => 'required|string',
            'reportable_id'   => 'required|integer',
            'reason'          => 'required|in:spam,illegal,obscene,advertisement,scam,inappropriate,hate,false_info,other',
            'description'     => 'nullable|string|max:500',
        ]);

        $typeMap = [
            'post'        => 'App\\Models\\Post',
            'comment'     => 'App\\Models\\Comment',
            'job'         => 'App\\Models\\JobPost',
            'market'      => 'App\\Models\\MarketItem',
            'business'    => 'App\\Models\\Business',
            'user'        => 'App\\Models\\User',
            'event'       => 'App\\Models\\Event',
            'recipe'      => 'App\\Models\\RecipePost',
            'qa'          => 'App\\Models\\QaPost',
            'short'       => 'App\\Models\\Short',
        ];

        $reportableType = $typeMap[$request->reportable_type] ?? $request->reportable_type;

        // Prevent duplicate reports
        $exists = Report::where('reporter_id', auth()->id())
            ->where('reportable_type', $reportableType)
            ->where('reportable_id', $request->reportable_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => '이미 신고했습니다.',
            ], 400);
        }

        Report::create([
            'reporter_id'     => auth()->id(),
            'reportable_type' => $reportableType,
            'reportable_id'   => $request->reportable_id,
            'reason'          => $request->reason,
            'content'         => $request->description,
        ]);

        // Count total reports for this content
        $reportCount = Report::where('reportable_type', $reportableType)
            ->where('reportable_id', $request->reportable_id)
            ->count();

        // 3+ reports -> auto-hide content
        if ($reportCount >= 3) {
            $this->hideContent($reportableType, $request->reportable_id);
        }

        // 5+ reports -> notify admins
        if ($reportCount >= 5) {
            $this->notifyAdmins($request->reportable_type, $request->reportable_id, $reportCount);
        }

        return response()->json([
            'success' => true,
            'message' => '신고가 접수되었습니다. 검토 후 처리하겠습니다.',
        ], 201);
    }

    /**
     * Hide content by setting status to 'hidden'.
     */
    private function hideContent(string $modelClass, int $id): void
    {
        try {
            $model = $modelClass::find($id);
            if ($model) {
                // Posts and comments use is_hidden
                if (isset($model->is_hidden)) {
                    $model->update(['is_hidden' => true]);
                }
            }
        } catch (\Exception $e) {
            // Silently handle
        }
    }

    /**
     * Notify all admins about heavily reported content.
     */
    private function notifyAdmins(string $type, int $id, int $count): void
    {
        try {
            $adminIds = DB::table('users')->where('role', 'admin')->pluck('id');
            foreach ($adminIds as $adminId) {
                Notification::create([
                    'user_id' => $adminId,
                    'type'    => 'report_alert',
                    'title'   => '신고 누적 콘텐츠',
                    'content' => "{$type} #{$id}에 신고가 {$count}회 누적되었습니다.",
                    'url'     => '/admin/reports',
                ]);
            }
        } catch (\Exception $e) {
            // Silently handle
        }
    }
}
