<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\QaPost;
use App\Models\QaAnswer;
use App\Models\QaCategory;
use Illuminate\Http\Request;

class QaController extends Controller
{
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

        return response()->json(['success' => true, 'data' => $query->paginate(20)]);
    }

    public function show($id)
    {
        $post = QaPost::with('user:id,name,nickname,avatar', 'category:id,name', 'answers.user:id,name,nickname,avatar')
            ->findOrFail($id);
        $post->increment('view_count');
        return response()->json(['success' => true, 'data' => $post]);
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

    public function categories()
    {
        return response()->json(['success' => true, 'data' => QaCategory::orderBy('sort_order')->get()]);
    }
}
