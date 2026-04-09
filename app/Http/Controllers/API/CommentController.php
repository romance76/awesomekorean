<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    private function resolveType($type)
    {
        return match($type) {
            'post' => 'App\\Models\\Post',
            'recipe' => 'App\\Models\\RecipePost',
            'club_post' => 'App\\Models\\ClubPost',
            'market' => 'App\\Models\\MarketItem',
            'qa' => 'App\\Models\\QaPost',
            'job' => 'App\\Models\\JobPost',
            'event' => 'App\\Models\\Event',
            'groupbuy' => 'App\\Models\\GroupBuy',
            'realestate' => 'App\\Models\\RealEstateListing',
            default => null,
        };
    }

    public function index($type, $id)
    {
        $modelType = $this->resolveType($type);
        if (!$modelType) return response()->json(['success' => false, 'message' => 'Invalid type'], 400);

        $comments = Comment::with('user:id,name,nickname,avatar', 'replies.user:id,name,nickname,avatar')
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

        $modelType = $this->resolveType($request->commentable_type);
        if (!$modelType) return response()->json(['success' => false, 'message' => 'Invalid type'], 400);

        $comment = Comment::create([
            'commentable_type' => $modelType,
            'commentable_id' => $request->commentable_id,
            'user_id' => auth()->id(),
            'parent_id' => $request->parent_id,
            'content' => $request->content,
        ]);

        // 댓글 작성 포인트 +3 (일일 최대 10회)
        $todayComments = Comment::where('user_id', auth()->id())->whereDate('created_at', today())->count();
        if ($todayComments <= 10) {
            auth()->user()->addPoints(3, '댓글 작성');
        }

        // Increment comment count on parent model
        $parent = $modelType::find($request->commentable_id);
        if ($parent && method_exists($parent, 'increment')) {
            try { $parent->increment('comment_count'); } catch (\Exception $e) {}
        }

        return response()->json(['success' => true, 'data' => $comment->load('user:id,name,nickname,avatar')], 201);
    }

    public function update(Request $request, $id)
    {
        $comment = Comment::where('user_id', auth()->id())->findOrFail($id);
        $comment->update(['content' => $request->content]);
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
