<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AmazonProduct;
use App\Models\Notification;
use App\Models\Report;
use App\Support\ShoppingReviews;
use App\Support\WritePoints;
use Illuminate\Http\Request;

/**
 * 회원 내돈내산 리뷰 관리자 — 첫 리뷰 승인/반려, 신고 누적·문제 리뷰 내리기, 복구.
 */
class AdminShoppingReviewController extends Controller
{
    private function notify(AmazonProduct $p, string $title, string $content): void
    {
        try { Notification::create(['user_id' => $p->user_id, 'type' => 'shopping_review', 'title' => $title, 'content' => $content, 'data' => ['product_id' => $p->id]]); } catch (\Throwable $e) {}
    }

    /** GET /admin/shopping-reviews?status=pending|published|hidden|rejected|all */
    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');
        $q = AmazonProduct::whereNotNull('user_id')->with('user:id,name,nickname,email,amazon_tag')->orderByDesc('id');
        if ($status !== 'all') $q->where('status', $status);
        if ($request->filled('search')) $q->where('title', 'like', '%' . $request->search . '%');

        $page = $q->paginate(20);
        $ids = $page->getCollection()->pluck('id');
        $reports = Report::where('reportable_type', AmazonProduct::class)->whereIn('reportable_id', $ids)
            ->selectRaw('reportable_id, COUNT(DISTINCT reporter_id) c')->groupBy('reportable_id')->pluck('c', 'reportable_id');
        $page->getCollection()->transform(function (AmazonProduct $p) use ($reports) {
            $a = $p->toArray();
            $a['reports'] = (int) ($reports[$p->id] ?? 0);
            return $a;
        });

        return response()->json([
            'success' => true, 'data' => $page,
            'counts' => [
                'pending' => AmazonProduct::whereNotNull('user_id')->where('status', 'pending')->count(),
                'hidden' => AmazonProduct::whereNotNull('user_id')->where('status', 'hidden')->count(),
            ],
        ]);
    }

    /** 승인 → 공개 + (처음 공개되는 거라면) 작성 포인트 */
    public function approve($id)
    {
        $p = AmazonProduct::whereNotNull('user_id')->findOrFail($id);
        if (!in_array($p->status, ['pending', 'rejected'], true)) {
            return response()->json(['success' => false, 'message' => '승인 대기 중인 리뷰만 승인할 수 있어요.'], 422);
        }
        $first = $p->published_at === null;
        $p->update(['status' => 'published', 'published_at' => $p->published_at ?? now(), 'admin_note' => null]);
        if ($first && $p->user) {
            try { WritePoints::award($p->user, AmazonProduct::class, $p->id, '내돈내산 리뷰 작성'); } catch (\Throwable $e) {}
        }
        ShoppingReviews::forget();
        $this->notify($p, '내돈내산 리뷰가 승인됐어요', "'{$p->title}' 리뷰가 공개되었어요. 앞으로 쓰는 리뷰는 바로 공개돼요.");
        return response()->json(['success' => true, 'message' => '승인했어요.']);
    }

    public function reject(Request $request, $id)
    {
        $request->validate(['reason' => 'nullable|string|max:200']);
        $p = AmazonProduct::whereNotNull('user_id')->findOrFail($id);
        $reason = $request->input('reason') ?: '리뷰 작성 규칙에 맞지 않아 반려되었어요.';
        $p->update(['status' => 'rejected', 'admin_note' => $reason]);
        ShoppingReviews::forget();
        $this->notify($p, '내돈내산 리뷰가 반려됐어요', "'{$p->title}' — {$reason} 내용을 고쳐서 다시 올려주세요.");
        return response()->json(['success' => true, 'message' => '반려했어요.']);
    }

    /** 문제 있는 리뷰 내리기 (신고 확인 후) */
    public function hide(Request $request, $id)
    {
        $request->validate(['reason' => 'nullable|string|max:200']);
        $p = AmazonProduct::whereNotNull('user_id')->findOrFail($id);
        $reason = $request->input('reason') ?: '신고 확인 후 관리자가 내렸어요.';
        $p->update(['status' => 'hidden', 'admin_note' => $reason]);
        ShoppingReviews::forget();
        $this->notify($p, '내돈내산 리뷰가 내려갔어요', "'{$p->title}' — {$reason}");
        return response()->json(['success' => true, 'message' => '내렸어요.']);
    }

    /** 내려간 리뷰 복구 (신고가 잘못된 경우) — 접수된 신고도 처리 완료로 */
    public function restore($id)
    {
        $p = AmazonProduct::whereNotNull('user_id')->findOrFail($id);
        $p->update(['status' => 'published', 'published_at' => $p->published_at ?? now(), 'admin_note' => null]);
        // 접수된 신고를 해결 처리 — 이력(누가·언제)도 같이 남긴다
        Report::where('reportable_type', AmazonProduct::class)->where('reportable_id', $p->id)->where('status', 'pending')->get()
            ->each(fn (Report $rp) => $rp->applyUpdate('resolved', '리뷰 복구로 자동 해결', auth()->user()));
        ShoppingReviews::forget();
        $this->notify($p, '내돈내산 리뷰가 다시 공개됐어요', "'{$p->title}' 리뷰를 확인했고 문제가 없어 다시 공개했어요.");
        return response()->json(['success' => true, 'message' => '복구했어요.']);
    }
}
