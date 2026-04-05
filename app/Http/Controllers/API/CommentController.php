<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommentController extends Controller
{
    /**
     * Map short type names to model classes for polymorphic comments.
     */
    private function resolveType(string $type): ?string
    {
        $map = [
            'post'        => 'App\\Models\\Post',
            'job'         => 'App\\Models\\JobPost',
            'market'      => 'App\\Models\\MarketItem',
            'realestate'  => 'App\\Models\\RealEstateListing',
            'event'       => 'App\\Models\\Event',
            'recipe'      => 'App\\Models\\RecipePost',
            'qa'          => 'App\\Models\\QaPost',
            'news'        => 'App\\Models\\News',
            'business'    => 'App\\Models\\Business',
            'short'       => 'App\\Models\\Short',
            'club_post'   => 'App\\Models\\ClubPost',
        ];

        return $map[$type] ?? null;
    }

    /**
     * GET /api/comments/{type}/{id}
     * Get comments for a commentable entity, threaded.
     * Comments use is_hidden (not status).
     */
    public function index($type, $id)
    {
        $modelClass = $this->resolveType($type);
        if (!$modelClass) {
            return response()->json(['success' => false, 'message' => '잘못된 댓글 유형입니다.'], 400);
        }

        $comments = Comment::where('commentable_type', $modelClass)
            ->where('commentable_id', $id)
            ->whereNull('parent_id')
            ->where('is_hidden', false)
            ->with([
                'user:id,name,nickname,avatar',
                'replies' => function ($q) {
                    $q->where('is_hidden', false)
                      ->with('user:id,name,nickname,avatar')
                      ->orderBy('created_at');
                },
            ])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $comments,
        ]);
    }

    /**
     * POST /api/comments
     * Create a comment for any commentable type.
     */
    public function store(Request $request)
    {
        $request->validate([
            'commentable_type' => 'required|string',
            'commentable_id'   => 'required|integer',
            'content'          => 'required|string|max:2000',
            'parent_id'        => 'nullable|exists:comments,id',
        ]);

        $modelClass = $this->resolveType($request->commentable_type);
        if (!$modelClass) {
            return response()->json(['success' => false, 'message' => '잘못된 댓글 유형입니다.'], 400);
        }

        // Verify the parent entity exists
        if (!$modelClass::find($request->commentable_id)) {
            return response()->json(['success' => false, 'message' => '대상을 찾을 수 없습니다.'], 404);
        }

        $comment = Comment::create([
            'commentable_type' => $modelClass,
            'commentable_id'   => $request->commentable_id,
            'user_id'          => auth()->id(),
            'parent_id'        => $request->parent_id,
            'content'          => $request->content,
        ]);

        // Increment comment_count on parent if it has that column
        try {
            $parent = $modelClass::find($request->commentable_id);
            if ($parent && isset($parent->comment_count)) {
                $parent->increment('comment_count');
            }
        } catch (\Exception $e) {
            // Column may not exist, ignore
        }

        // Notify the parent content's author
        try {
            $parent = $modelClass::find($request->commentable_id);
            if ($parent && isset($parent->user_id) && $parent->user_id !== auth()->id()) {
                Notification::create([
                    'user_id' => $parent->user_id,
                    'type'    => 'comment',
                    'title'   => '새 댓글이 달렸습니다',
                    'content' => auth()->user()->name . '님: ' . mb_substr($request->content, 0, 50),
                ]);
            }
        } catch (\Exception $e) {
            // Silently handle notification failures
        }

        return response()->json([
            'success' => true,
            'message' => '댓글이 등록되었습니다.',
            'data'    => $comment->load('user:id,name,nickname,avatar'),
        ], 201);
    }

    /**
     * PUT /api/comments/{id}
     */
    public function update(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);

        if ($comment->user_id !== auth()->id() && !(auth()->user() && auth()->user()->role === 'admin')) {
            return response()->json(['success' => false, 'message' => '수정 권한이 없습니다.'], 403);
        }

        $request->validate(['content' => 'required|string|max:2000']);
        $comment->update(['content' => $request->content]);

        return response()->json([
            'success' => true,
            'message' => '댓글이 수정되었습니다.',
            'data'    => $comment->load('user:id,name,nickname,avatar'),
        ]);
    }

    /**
     * DELETE /api/comments/{id}
     * Hide comment using is_hidden (not status)
     */
    public function destroy($id)
    {
        $comment = Comment::findOrFail($id);

        if ($comment->user_id !== auth()->id() && !(auth()->user() && auth()->user()->role === 'admin')) {
            return response()->json(['success' => false, 'message' => '삭제 권한이 없습니다.'], 403);
        }

        // Decrement parent entity's comment_count
        try {
            $parent = $comment->commentable;
            if ($parent && isset($parent->comment_count)) {
                $parent->decrement('comment_count');
            }
        } catch (\Exception $e) {
            // Ignore
        }

        $comment->update(['is_hidden' => true]);
        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => '삭제되었습니다.',
        ]);
    }

    /**
     * POST /api/comments/{id}/like
     */
    public function toggleLike($id)
    {
        $comment = Comment::findOrFail($id);
        $userId = auth()->id();

        $existing = DB::table('comment_likes')
            ->where('comment_id', $comment->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            DB::table('comment_likes')->where('id', $existing->id)->delete();
            $comment->decrement('like_count');
            return response()->json([
                'success' => true,
                'data'    => ['liked' => false, 'like_count' => $comment->fresh()->like_count],
            ]);
        }

        DB::table('comment_likes')->insert([
            'comment_id' => $comment->id,
            'user_id'    => $userId,
            'created_at' => now(),
        ]);
        $comment->increment('like_count');

        return response()->json([
            'success' => true,
            'data'    => ['liked' => true, 'like_count' => $comment->fresh()->like_count],
        ]);
    }
}
