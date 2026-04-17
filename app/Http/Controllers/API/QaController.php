<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\QaPost;
use App\Models\QaAnswer;
use App\Models\QaCategory;
use Illuminate\Http\Request;

class QaController extends Controller
{
    use \App\Traits\HasAdjacent;

    public function index(Request $request)
    {
        $query = QaPost::with('user:id,name,nickname,avatar', 'category:id,name')
            ->when($request->category_id, fn($q, $v) => $q->where('category_id', $v))
            ->when($request->search, fn($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->when($request->resolved !== null, fn($q) => $q->where('is_resolved', $request->boolean('resolved')));

        $sort = $request->sort ?? 'latest';
        if ($sort === 'bounty') $query->orderByDesc('bounty_points');
        elseif ($sort === 'popular') $query->orderByDesc('view_count');
        else $query->orderByDesc('created_at');

        return response()->json(['success' => true, 'data' => $query->paginate($request->per_page ?? 20)]);
    }

    public function show($id)
    {
        $post = QaPost::with('user:id,name,nickname,avatar', 'category:id,name', 'answers.user:id,name,nickname,avatar')
            ->findOrFail($id);
        $post->increment('view_count');

        // 로그인 시 각 답변의 내 투표 상태
        if (auth('api')->check()) {
            $userId = auth('api')->id();
            $votes = \DB::table('qa_answer_likes')
                ->where('user_id', $userId)
                ->whereIn('qa_answer_id', $post->answers->pluck('id'))
                ->pluck('type', 'qa_answer_id');
            $post->answers->each(function ($ans) use ($votes) {
                $ans->_vote = $votes[$ans->id] ?? null;
            });
        }

        $adj = $this->adjacentPair(QaPost::class, $id, 'title', ['category_id' => $post->category_id]);
        return response()->json(['success' => true, 'data' => $post, 'prev' => $adj['prev'], 'next' => $adj['next']]);
    }

    public function store(Request $request)
    {
        $request->validate(['title' => 'required|max:200', 'content' => 'required']);

        $bounty = min($request->bounty_points ?? 0, auth()->user()->points);

        $post = QaPost::create([
            'user_id' => auth()->id(),
            'category_id' => $request->category_id,
            'title' => $request->title,
            'content' => $request->content,
            'bounty_points' => $bounty,
        ]);

        if ($bounty > 0) {
            auth()->user()->decrement('points', $bounty);
        }

        return response()->json(['success' => true, 'data' => $post], 201);
    }

    public function answer(Request $request, $id)
    {
        $request->validate(['content' => 'required']);
        $post = QaPost::findOrFail($id);

        $answer = QaAnswer::create([
            'qa_post_id' => $id,
            'user_id' => auth()->id(),
            'content' => $request->content,
        ]);

        $post->increment('answer_count');
        return response()->json(['success' => true, 'data' => $answer->load('user:id,name,nickname,avatar')], 201);
    }

    public function acceptAnswer($id, $answerId)
    {
        $post = QaPost::where('user_id', auth()->id())->findOrFail($id);
        $answer = QaAnswer::where('qa_post_id', $id)->findOrFail($answerId);

        $post->update(['is_resolved' => true, 'best_answer_id' => $answerId]);
        $answer->update(['is_best' => true]);

        // 바운티 포인트 지급 (addPoints로 point_logs에 기록)
        if ($post->bounty_points > 0) {
            $answer->user->addPoints($post->bounty_points, "Q&A 바운티: {$post->title}", 'earn');
        }

        // Q&A 채택 보너스 +20P (point_settings에서 값 로드)
        $acceptBonus = (int) (\DB::table('point_settings')->where('key', 'qa_answer_accepted')->value('value') ?? 20);
        if ($acceptBonus > 0) {
            $answer->user->addPoints($acceptBonus, "Q&A 채택 보너스: {$post->title}", 'earn');
        }

        return response()->json(['success' => true, 'message' => '채택되었습니다']);
    }

    // 답변 삭제 (본인만)
    public function deleteAnswer($id, $answerId)
    {
        $answer = QaAnswer::where('qa_post_id', $id)
            ->where('id', $answerId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $answer->delete();
        QaPost::find($id)?->decrement('answer_count');

        return response()->json(['success' => true, 'message' => '답변이 삭제되었습니다.']);
    }

    // 답변 좋아요/싫어요 토글
    public function likeAnswer(Request $request, $id, $answerId)
    {
        $answer = QaAnswer::where('qa_post_id', $id)->findOrFail($answerId);
        $userId = auth()->id();
        $type = $request->input('type', 'like'); // 'like' or 'dislike'

        $existing = \DB::table('qa_answer_likes')
            ->where('qa_answer_id', $answerId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            $oldType = $existing->type ?? 'like';
            if ($oldType === $type) {
                // 같은 버튼 다시 → 취소
                \DB::table('qa_answer_likes')->where('id', $existing->id)->delete();
                $answer->decrement($type === 'like' ? 'like_count' : 'dislike_count');
            } else {
                // 반대 버튼 → 전환
                \DB::table('qa_answer_likes')->where('id', $existing->id)->update(['type' => $type, 'created_at' => now()]);
                $answer->decrement($oldType === 'like' ? 'like_count' : 'dislike_count');
                $answer->increment($type === 'like' ? 'like_count' : 'dislike_count');
            }
        } else {
            \DB::table('qa_answer_likes')->insert([
                'qa_answer_id' => $answerId,
                'user_id' => $userId,
                'type' => $type,
                'created_at' => now(),
            ]);
            $answer->increment($type === 'like' ? 'like_count' : 'dislike_count');
        }

        $fresh = $answer->fresh();
        $myVote = \DB::table('qa_answer_likes')
            ->where('qa_answer_id', $answerId)
            ->where('user_id', $userId)
            ->value('type');

        return response()->json([
            'success' => true,
            'my_vote' => $myVote,
            'like_count' => $fresh->like_count,
            'dislike_count' => $fresh->dislike_count ?? 0,
        ]);
    }

    public function categories()
    {
        return response()->json(['success' => true, 'data' => QaCategory::orderBy('sort_order')->get()]);
    }
}
