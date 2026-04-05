<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\QaPost;
use App\Models\QaAnswer;
use App\Models\QaCategory;
use Illuminate\Http\Request;

class AdminQaController extends Controller
{
    /**
     * GET /api/admin/qa
     * Q&A 게시글 목록 (검색/카테고리/상태 필터)
     */
    public function index(Request $request)
    {
        try {
            $query = QaPost::with('user:id,name,nickname', 'category:id,name,key');

            if ($search = $request->input('search')) {
                $query->where('title', 'like', "%{$search}%");
            }
            if ($categoryId = $request->input('category_id')) {
                $query->where('category_id', $categoryId);
            }
            if ($status = $request->input('status')) {
                if ($status === 'solved' || $status === 'resolved') {
                    $query->where('is_resolved', true);
                } elseif ($status === 'pending') {
                    $query->where('is_resolved', false);
                }
            }

            $posts = $query->orderByDesc('created_at')->paginate($request->input('per_page', 25));

            $stats = [
                'total'   => QaPost::count(),
                'solved'  => QaPost::where('is_resolved', true)->count(),
                'pending' => QaPost::where('is_resolved', false)->count(),
                'answers' => QaAnswer::count(),
            ];

            return response()->json(['success' => true, 'data' => $posts, 'stats' => $stats]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/admin/qa/{id}
     * Q&A 게시글 삭제
     */
    public function destroy($id)
    {
        try {
            $post = QaPost::findOrFail($id);

            // 연관 답변도 삭제
            QaAnswer::where('qa_post_id', $id)->delete();
            $post->delete();

            return response()->json(['success' => true, 'message' => 'Q&A 게시글이 삭제되었습니다.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
