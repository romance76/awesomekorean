<?php

namespace App\Http\Controllers\API;

use App\Events\NewNotification;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Notification;
use App\Models\RecipePost;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * 게시판별 포인트 재설계 — 이벤트 완료인증 / 레시피 인기보상.
 * 둘 다 자동 지급이 아니라 관리자가 확인 후 직접 지급하는 방식(사용자 결정).
 */
class AdminRewardController extends Controller
{
    public function eventProofs()
    {
        $items = EventAttendee::with(['user:id,name,nickname,avatar', 'event:id,title,reward_points'])
            ->where('proof_status', 'pending')
            ->orderBy('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function approveEventProof($id)
    {
        $attendee = EventAttendee::with('event')->findOrFail($id);
        if ($attendee->proof_status !== 'pending') {
            return response()->json(['success' => false, 'message' => '대기중인 제출이 아닙니다'], 422);
        }

        $attendee->update(['proof_status' => 'approved', 'reviewed_at' => now()]);

        $amount = (int) ($attendee->event->reward_points ?? 0);
        $user = User::find($attendee->user_id);
        if ($user && $amount > 0) {
            $user->addPoints($amount, "이벤트 완료 인증: {$attendee->event->title}", 'event_proof_approved', ['type' => Event::class, 'id' => $attendee->event_id]);
        }

        $this->notify($attendee->user_id, 'event_proof_approved', '이벤트 완료 인증이 승인되었습니다', "'{$attendee->event->title}' 완료 인증이 승인되어 {$amount}P가 지급되었습니다.", ['event_id' => $attendee->event_id]);

        return response()->json(['success' => true, 'data' => $attendee->fresh()]);
    }

    public function rejectEventProof(Request $request, $id)
    {
        $attendee = EventAttendee::with('event')->findOrFail($id);
        if ($attendee->proof_status !== 'pending') {
            return response()->json(['success' => false, 'message' => '대기중인 제출이 아닙니다'], 422);
        }

        $attendee->update(['proof_status' => 'rejected', 'reviewed_at' => now()]);

        $reason = $request->input('reason', '');
        $this->notify($attendee->user_id, 'event_proof_rejected', '이벤트 완료 인증이 반려되었습니다', "'{$attendee->event->title}' 완료 인증이 반려되었습니다." . ($reason ? " 사유: {$reason}" : ' 다시 제출해주세요.'), ['event_id' => $attendee->event_id]);

        return response()->json(['success' => true, 'data' => $attendee->fresh()]);
    }

    /**
     * 좋아요(찜) 5개 이상 또는 댓글 5개 이상 받은, 아직 보상 미지급 레시피 목록.
     */
    public function recipeRewardCandidates()
    {
        $items = RecipePost::whereNull('reward_paid_at')
            ->with('user:id,name,nickname,avatar')
            ->withCount('comments')
            ->get()
            ->filter(fn ($r) => $r->favorite_count >= 5 || $r->comments_count >= 5)
            ->values();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function payRecipeReward(Request $request, $id)
    {
        $request->validate(['amount' => 'required|integer|min:1']);

        $recipe = RecipePost::findOrFail($id);
        if ($recipe->reward_paid_at) {
            return response()->json(['success' => false, 'message' => '이미 지급된 레시피입니다'], 422);
        }

        $recipe->update(['reward_paid_at' => now(), 'reward_amount' => $request->amount]);

        $user = User::find($recipe->user_id);
        $user?->addPoints($request->amount, "레시피 인기 보상: {$recipe->title}", 'recipe_reward', ['type' => RecipePost::class, 'id' => $recipe->id]);

        $this->notify($recipe->user_id, 'recipe_reward', '레시피 인기 보상이 지급되었습니다', "'{$recipe->title}' 레시피가 많은 관심을 받아 {$request->amount}P가 지급되었습니다.", ['recipe_id' => $recipe->id]);

        return response()->json(['success' => true, 'data' => $recipe->fresh()]);
    }

    private function notify(int $userId, string $type, string $title, string $content, array $data = []): void
    {
        try {
            Notification::create(['user_id' => $userId, 'type' => $type, 'title' => $title, 'content' => $content, 'data' => $data]);
            $unread = Notification::where('user_id', $userId)->whereNull('read_at')->count();
            broadcast(new NewNotification($userId, $unread, $title))->toOthers();
        } catch (\Exception $e) {}
    }
}
