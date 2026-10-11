<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Services\BadWordFilter;
use App\Support\PlainText;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    private function resolveType($type)
    {
        return match($type) {
            'post' => 'App\\Models\\Post',
            'recipe' => 'App\\Models\\RecipePost',
            'club_post' => 'App\\Models\\ClubPost',
            'club' => 'App\\Models\\Club',
            'market' => 'App\\Models\\MarketItem',
            'qa' => 'App\\Models\\QaPost',
            'job' => 'App\\Models\\JobPost',
            'event' => 'App\\Models\\Event',
            'news' => 'App\\Models\\News',
            'groupbuy' => 'App\\Models\\GroupBuy',
            'realestate' => 'App\\Models\\RealEstateListing',
            'business' => 'App\\Models\\Business',
            'shopping' => 'App\\Models\\AmazonProduct',
            default => null,
        };
    }

    public function index($type, $id)
    {
        $modelType = $this->resolveType($type);
        if (!$modelType) return response()->json(['success' => false, 'message' => 'Invalid type'], 400);

        $comments = Comment::with('user:id,name,nickname,avatar,lifetime_points', 'replies.user:id,name,nickname,avatar,lifetime_points')
            ->where('commentable_type', $modelType)
            ->where('commentable_id', $id)
            ->whereNull('parent_id')
            ->where('is_hidden', false)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $comments]);
    }

    public function store(Request $request)
    {
        $request->validate(['commentable_type' => 'required', 'commentable_id' => 'required|integer', 'content' => 'required|max:1000']);

        if (BadWordFilter::contains((string) $request->content)) {
            return response()->json(['success' => false, 'message' => '부적절한 표현이 포함되어 있어 댓글을 작성할 수 없습니다.'], 422);
        }

        $modelType = $this->resolveType($request->commentable_type);
        if (!$modelType) return response()->json(['success' => false, 'message' => 'Invalid type'], 400);

        // 없는 글·지워진 글에는 댓글을 달 수 없다 (조회 자체가 실패하는 예외 상황이면 이 검사만 건너뜀)
        try {
            if (!$modelType::whereKey((int) $request->commentable_id)->exists()) {
                return response()->json(['success' => false, 'message' => '댓글을 달 글을 찾을 수 없어요. 이미 지워졌을 수 있어요.'], 404);
            }
        } catch (\Throwable $e) { report($e); }
        // 답글이면 부모 댓글이 같은 글의 댓글이어야 한다
        if ($request->filled('parent_id')) {
            $parent = Comment::where('id', (int) $request->parent_id)->first();
            if (!$parent || $parent->commentable_type !== $modelType || (int) $parent->commentable_id !== (int) $request->commentable_id) {
                return response()->json(['success' => false, 'message' => '답글을 달 댓글을 찾을 수 없어요.'], 422);
            }
        }

        // HTML 태그는 저장하지 않는다 (글자만) — 태그만 쓴 댓글은 빈 댓글이 되므로 거절
        $cleanContent = PlainText::clean((string) $request->content);
        if (trim((string) $cleanContent) === '') return response()->json(['success' => false, 'message' => '댓글 내용을 입력해 주세요.'], 422);

        $comment = Comment::create([
            'commentable_type' => $modelType,
            'commentable_id' => $request->commentable_id,
            'user_id' => auth()->id(),
            'parent_id' => $request->parent_id,
            'content' => $cleanContent,
        ]);

        // 댓글 수 증가 — 부모를 먼저 읽지 않고 UPDATE 한 번으로 (comment_count 컬럼이 없는 모델은 조용히 무시)
        try { $modelType::whereKey($request->commentable_id)->increment('comment_count'); } catch (\Throwable $e) {}

        $me = auth()->user();
        $comment->load('user:id,name,nickname,avatar,lifetime_points');

        // 댓글 작성 포인트(+활동 뱃지·Entry 보상) — 게시판 종류 무관 사이트 전체 통합 한도/금액 (WritePoints).
        // 포인트 계산 쿼리가 여러 번 나가 응답이 느려지므로 응답을 먼저 보낸 뒤 처리한다.
        $commentId = $comment->id;
        defer(function () use ($me, $commentId) {
            try {
                if ($me) \App\Support\WritePoints::award($me, \App\Models\Comment::class, $commentId, '댓글 작성');
            } catch (\Throwable $e) {
                \Log::warning('[댓글] 포인트 지급 실패: ' . $e->getMessage());
            }
        });

        return response()->json(['success' => true, 'data' => $comment], 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate(['content' => 'required|max:1000']);
        if (BadWordFilter::contains((string) $request->content)) {
            return response()->json(['success' => false, 'message' => '부적절한 표현이 포함되어 있어 댓글을 수정할 수 없습니다.'], 422);
        }
        $cleanContent = trim((string) PlainText::clean((string) $request->content));
        if ($cleanContent === '') return response()->json(['success' => false, 'message' => '댓글 내용을 입력해 주세요.'], 422);
        $comment = Comment::where('user_id', auth()->id())->findOrFail($id);
        $comment->update(['content' => $cleanContent]);
        return response()->json(['success' => true, 'data' => $comment]);
    }

    public function destroy($id)
    {
        $comment = Comment::where('user_id', auth()->id())->findOrFail($id);
        $comment->update(['is_hidden' => true]);
        return response()->json(['success' => true, 'message' => '삭제되었습니다']);
    }

    public function vote(Request $request, $id)
    {
        $request->validate(['vote' => 'required|in:like,dislike']);
        $comment = Comment::findOrFail($id);
        $existing = \DB::table('comment_votes')->where('comment_id', $id)->where('user_id', auth()->id())->first();

        if ($existing) {
            if ($existing->vote === $request->vote) {
                // 같은 투표 → 취소
                \DB::table('comment_votes')->where('id', $existing->id)->delete();
                $comment->decrement($request->vote === 'like' ? 'likes' : 'dislikes');
                return response()->json(['success' => true, 'action' => 'removed', 'likes' => $comment->fresh()->likes, 'dislikes' => $comment->fresh()->dislikes]);
            }
            // 다른 투표 → 전환
            \DB::table('comment_votes')->where('id', $existing->id)->update(['vote' => $request->vote, 'updated_at' => now()]);
            $comment->increment($request->vote === 'like' ? 'likes' : 'dislikes');
            $comment->decrement($request->vote === 'like' ? 'dislikes' : 'likes');
        } else {
            \DB::table('comment_votes')->insert(['comment_id' => $id, 'user_id' => auth()->id(), 'vote' => $request->vote, 'created_at' => now(), 'updated_at' => now()]);
            $comment->increment($request->vote === 'like' ? 'likes' : 'dislikes');
        }

        return response()->json(['success' => true, 'action' => $request->vote, 'likes' => $comment->fresh()->likes, 'dislikes' => $comment->fresh()->dislikes]);
    }
}
