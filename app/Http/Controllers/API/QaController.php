<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\QaQuestion;
use App\Models\QaQuestionAnswer;
use App\Models\QaQuestionRecommend;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QAController extends Controller
{
    private $categories = [
        ['slug'=>'immigration','name'=>'이민·비자·영주권','icon'=>'🛂','description'=>'비자·영주권·시민권·DACA'],
        ['slug'=>'tax','name'=>'세금·회계','icon'=>'💰','description'=>'소득세·비즈니스세·IRS'],
        ['slug'=>'medical','name'=>'의료·보험','icon'=>'🏥','description'=>'건강보험·병원·처방전'],
        ['slug'=>'jobs','name'=>'취업·직장','icon'=>'💼','description'=>'취업·OPT·워킹비자·이직'],
        ['slug'=>'realestate','name'=>'부동산·렌트','icon'=>'🏠','description'=>'렌트·집구매·모기지'],
        ['slug'=>'car','name'=>'운전·자동차','icon'=>'🚗','description'=>'운전면허·보험·차구매'],
        ['slug'=>'education','name'=>'학교·교육','icon'=>'🎓','description'=>'대학입시·유학·학자금'],
        ['slug'=>'business','name'=>'창업·비즈니스','icon'=>'🏪','description'=>'소규모 창업·LLC·허가'],
        ['slug'=>'finance','name'=>'금융·투자·신용','icon'=>'📈','description'=>'크레딧·은행·투자'],
        ['slug'=>'general','name'=>'기타 생활','icon'=>'🙋','description'=>'위 카테고리에 없는 질문'],
    ];

    private $titles = [
        ['min_accepted' => 0,  'title' => '새싹 답변자'],
        ['min_accepted' => 5,  'title' => '도움이 답변자'],
        ['min_accepted' => 15, 'title' => '신뢰 답변자'],
        ['min_accepted' => 30, 'title' => '전문 답변자'],
        ['min_accepted' => 50, 'title' => '마스터 답변자'],
        ['min_accepted' => 100,'title' => '레전드 답변자'],
    ];

    public function categories()
    {
        $counts = \App\Models\QaQuestion::selectRaw('category_slug, COUNT(*) as cnt')
            ->whereNull('deleted_at')
            ->groupBy('category_slug')
            ->pluck('cnt', 'category_slug');
        
        $result = collect($this->categories)->map(function($cat) use ($counts) {
            $cat['count'] = $counts[$cat['slug']] ?? 0;
            return $cat;
        });
        
        return response()->json($result->values());
    }

    public function allQuestions()
    {
        $sort = request('sort', 'latest');
        $query = QaQuestion::with(['user:id,nickname,username,avatar,title']);

        switch ($sort) {
            case 'answers':
                $query->orderByDesc('answer_count');
                break;
            case 'resolved':
                $query->where('is_resolved', true)->orderByDesc('created_at');
                break;
            case 'recommend':
                $query->orderByDesc('recommend_count');
                break;
            default:
                $query->orderByDesc('created_at');
                break;
        }

        return response()->json($query->paginate(request('per_page', 20)));
    }

    public function questions($slug)
    {
        $validSlugs = array_column($this->categories, 'slug');
        if (!in_array($slug, $validSlugs)) {
            return response()->json(['message' => '유효하지 않은 카테고리입니다.'], 404);
        }

        $sort = request('sort', 'latest');

        $query = QaQuestion::with(['user:id,nickname,username,avatar,title'])
            ->where('category_slug', $slug);

        switch ($sort) {
            case 'answers':
                $query->orderByDesc('answer_count');
                break;
            case 'resolved':
                $query->where('is_resolved', true)->orderByDesc('created_at');
                break;
            case 'recommend':
                $query->orderByDesc('recommend_count');
                break;
            default:
                $query->orderByDesc('created_at');
                break;
        }

        return response()->json($query->paginate(20));
    }

    public function show($id)
    {
        $question = QaQuestion::with([
            'user:id,nickname,username,avatar,title',
            'answers' => function ($q) {
                $q->with('user:id,nickname,username,avatar,title,total_accepted')
                  ->orderByDesc('is_accepted')
                  ->orderByDesc('like_count')
                  ->orderBy('created_at');
            },
            'acceptedAnswer.user:id,nickname,username,avatar,title',
        ])->findOrFail($id);

        $question->increment('view_count');

        $question->is_recommended = false;
        if (Auth::check()) {
            $question->is_recommended = QaQuestionRecommend::where('question_id', $id)
                ->where('user_id', Auth::id())
                ->exists();
        }

        return response()->json($question);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category_slug' => 'required|string',
            'point_reward' => 'nullable|integer|min:0',
        ]);

        $validSlugs = array_column($this->categories, 'slug');
        if (!in_array($request->category_slug, $validSlugs)) {
            return response()->json(['message' => '유효하지 않은 카테고리입니다.'], 422);
        }

        $user = Auth::user();
        $pointReward = $request->point_reward ?? 0;

        if ($pointReward > 0) {
            if ($user->points_total < $pointReward) {
                return response()->json(['message' => '포인트가 부족합니다.'], 422);
            }
            $user->addPoints(-$pointReward, 'qa_question_reward', 'spend', null, "Q&A 질문 포인트 걸기: {$pointReward}P");
        }

        $question = QaQuestion::create([
            'user_id' => $user->id,
            'category_slug' => $request->category_slug,
            'title' => $request->title,
            'content' => $request->content,
            'point_reward' => $pointReward,
        ]);

        return response()->json($question->load('user:id,nickname,username,avatar'), 201);
    }

    public function update(Request $request, $id)
    {
        $question = QaQuestion::findOrFail($id);

        if ($question->user_id !== Auth::id()) {
            return response()->json(['message' => '권한이 없습니다.'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
        ]);

        $question->update($validated);
        return response()->json($question);
    }

    public function destroy($id)
    {
        $question = QaQuestion::findOrFail($id);

        if ($question->user_id !== Auth::id()) {
            return response()->json(['message' => '권한이 없습니다.'], 403);
        }

        $question->delete();
        return response()->json(['message' => '삭제되었습니다.']);
    }

    public function recommend($id)
    {
        $question = QaQuestion::findOrFail($id);

        $existing = QaQuestionRecommend::where('question_id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if ($existing) {
            $existing->delete();
            $question->decrement('recommend_count');
            return response()->json(['recommended' => false, 'recommend_count' => $question->fresh()->recommend_count]);
        } else {
            QaQuestionRecommend::create([
                'question_id' => $id,
                'user_id' => Auth::id(),
            ]);
            $question->increment('recommend_count');
            return response()->json(['recommended' => true, 'recommend_count' => $question->fresh()->recommend_count]);
        }
    }

    public function answers($questionId)
    {
        $answers = QaQuestionAnswer::where('question_id', $questionId)
            ->with('user:id,nickname,username,avatar,title,total_accepted')
            ->orderByDesc('is_accepted')
            ->orderByDesc('like_count')
            ->orderBy('created_at')
            ->get();

        return response()->json($answers);
    }

    public function storeAnswer(Request $request, $questionId)
    {
        $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $question = QaQuestion::findOrFail($questionId);

        $answer = QaQuestionAnswer::create([
            'question_id' => $questionId,
            'user_id' => Auth::id(),
            'content' => $request->content,
        ]);

        $question->increment('answer_count');

        Auth::user()->addPoints(5, 'qa_answer', 'earn', $answer->id, 'Q&A 답변 작성');

        return response()->json($answer->load('user:id,nickname,username,avatar,title'), 201);
    }

    public function acceptAnswer($answerId)
    {
        $answer = QaQuestionAnswer::with('user')->findOrFail($answerId);
        $question = QaQuestion::findOrFail($answer->question_id);

        if ($question->user_id !== Auth::id()) {
            return response()->json(['message' => '질문 작성자만 채택할 수 있습니다.'], 403);
        }

        if ($question->accepted_answer_id) {
            return response()->json(['message' => '이미 채택된 답변이 있습니다.'], 422);
        }

        if ($answer->user_id === Auth::id()) {
            return response()->json(['message' => '자신의 답변은 채택할 수 없습니다.'], 422);
        }

        DB::transaction(function () use ($answer, $question) {
            $answer->update(['is_accepted' => true]);

            $question->update([
                'accepted_answer_id' => $answer->id,
                'is_resolved' => true,
                'resolved_at' => now(),
            ]);

            // 포인트 보상: 기본 50P + 질문에 걸린 포인트
            $rewardPoints = 50 + ($question->point_reward ?? 0);
            $answer->user->addPoints($rewardPoints, 'qa_accepted', 'earn', $answer->id, "Q&A 답변 채택 보상: {$rewardPoints}P");

            $answer->user->increment('total_accepted');

            $this->updateUserTitle($answer->user_id);
        });

        return response()->json(['message' => '답변이 채택되었습니다.', 'answer' => $answer->fresh()->load('user:id,nickname,username,avatar,title')]);
    }

    public function likeAnswer($answerId)
    {
        $answer = QaQuestionAnswer::findOrFail($answerId);

        $existing = DB::table('qa_question_answer_likes')
            ->where('answer_id', $answerId)
            ->where('user_id', Auth::id())
            ->first();

        if ($existing) {
            DB::table('qa_question_answer_likes')->where('id', $existing->id)->delete();
            $answer->decrement('like_count');
            return response()->json(['liked' => false, 'like_count' => $answer->fresh()->like_count]);
        } else {
            DB::table('qa_question_answer_likes')->insert([
                'answer_id' => $answerId,
                'user_id' => Auth::id(),
                'created_at' => now(),
            ]);
            $answer->increment('like_count');
            return response()->json(['liked' => true, 'like_count' => $answer->fresh()->like_count]);
        }
    }

    public function leaderboard()
    {
        $period = request('period', 'month');

        if ($period === 'month') {
            $leaders = User::select('users.id', 'users.nickname', 'users.username', 'users.avatar', 'users.title')
                ->selectRaw('COUNT(qa_question_answers.id) as monthly_accepted')
                ->join('qa_question_answers', 'users.id', '=', 'qa_question_answers.user_id')
                ->where('qa_question_answers.is_accepted', true)
                ->where('qa_question_answers.created_at', '>=', now()->startOfMonth())
                ->groupBy('users.id', 'users.nickname', 'users.username', 'users.avatar', 'users.title')
                ->orderByDesc('monthly_accepted')
                ->limit(10)
                ->get();
        } else {
            $leaders = User::select('id', 'nickname', 'username', 'avatar', 'title', 'total_accepted')
                ->where('total_accepted', '>', 0)
                ->orderByDesc('total_accepted')
                ->limit(10)
                ->get();
        }

        return response()->json($leaders);
    }

    public function userResolved($userId)
    {
        $answers = QaQuestionAnswer::where('user_id', $userId)
            ->where('is_accepted', true)
            ->with(['user:id,nickname,username,avatar,title'])
            ->orderByDesc('created_at')
            ->paginate(20);

        $answers->getCollection()->transform(function ($answer) {
            $answer->question = QaQuestion::select('id', 'title', 'is_resolved')->find($answer->question_id);
            return $answer;
        });

        return response()->json($answers);
    }

    private function updateUserTitle($userId)
    {
        $user = User::find($userId);
        if (!$user) return;

        $accepted = $user->total_accepted;
        $newTitle = '새싹 답변자';

        foreach ($this->titles as $t) {
            if ($accepted >= $t['min_accepted']) {
                $newTitle = $t['title'];
            }
        }

        $user->update(['title' => $newTitle]);
    }
}
