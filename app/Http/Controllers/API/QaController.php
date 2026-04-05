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
    /**
     * Hardcoded category definitions
     */
    private array $categoryList = [
        ['slug' => 'immigration', 'name' => '이민·비자·영주권', 'icon' => "\xF0\x9F\x9B\x82", 'description' => '비자·영주권·시민권·DACA'],
        ['slug' => 'tax',         'name' => '세금·회계',         'icon' => "\xF0\x9F\x92\xB0", 'description' => '소득세·비즈니스세·IRS'],
        ['slug' => 'medical',     'name' => '의료·보험',         'icon' => "\xF0\x9F\x8F\xA5", 'description' => '건강보험·병원·처방전'],
        ['slug' => 'jobs',        'name' => '취업·직장',         'icon' => "\xF0\x9F\x92\xBC", 'description' => '취업·OPT·워킹비자·이직'],
        ['slug' => 'realestate',  'name' => '부동산·렌트',       'icon' => "\xF0\x9F\x8F\xA0", 'description' => '렌트·집구매·모기지'],
        ['slug' => 'car',         'name' => '운전·자동차',       'icon' => "\xF0\x9F\x9A\x97", 'description' => '운전면허·보험·차구매'],
        ['slug' => 'education',   'name' => '학교·교육',         'icon' => "\xF0\x9F\x8E\x93", 'description' => '대학입시·유학·학자금'],
        ['slug' => 'business',    'name' => '창업·비즈니스',     'icon' => "\xF0\x9F\x8F\xAA", 'description' => '소규모 창업·LLC·허가'],
        ['slug' => 'finance',     'name' => '금융·투자·신용',    'icon' => "\xF0\x9F\x93\x88", 'description' => '크레딧·은행·투자'],
        ['slug' => 'general',     'name' => '기타 생활',         'icon' => "\xF0\x9F\x99\x8B", 'description' => '위 카테고리에 없는 질문'],
    ];

    /**
     * Title progression for answer experts
     */
    private array $titleLevels = [
        ['min_accepted' => 0,   'title' => '새싹 답변자'],
        ['min_accepted' => 5,   'title' => '도움이 답변자'],
        ['min_accepted' => 15,  'title' => '신뢰 답변자'],
        ['min_accepted' => 30,  'title' => '전문 답변자'],
        ['min_accepted' => 50,  'title' => '마스터 답변자'],
        ['min_accepted' => 100, 'title' => '레전드 답변자'],
    ];

    /**
     * GET /api/qa/categories
     * List QA categories with question count
     */
    public function categories()
    {
        $counts = QaQuestion::selectRaw('category_slug, COUNT(*) as cnt')
            ->whereNull('deleted_at')
            ->groupBy('category_slug')
            ->pluck('cnt', 'category_slug');

        $result = collect($this->categoryList)->map(function ($cat) use ($counts) {
            $cat['count'] = $counts[$cat['slug']] ?? 0;
            return $cat;
        });

        return response()->json(['success' => true, 'data' => $result->values()]);
    }

    /**
     * GET /api/qa
     * List questions with category, is_resolved, sort (newest/bounty/popular)
     */
    public function index(Request $request)
    {
        $query = QaQuestion::with('user:id,nickname,nickname,avatar,title');

        // Category filter
        if ($request->filled('category')) {
            $query->where('category_slug', $request->category);
        }

        // Resolved filter
        if ($request->filled('is_resolved')) {
            $query->where('is_resolved', filter_var($request->is_resolved, FILTER_VALIDATE_BOOLEAN));
        }

        // Search
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('content', 'like', "%{$s}%");
            });
        }

        // Sort
        $sort = $request->input('sort', 'newest');
        switch ($sort) {
            case 'bounty':
                $query->orderByDesc('point_reward')->orderByDesc('created_at');
                break;
            case 'popular':
                $query->orderByDesc('recommend_count');
                break;
            case 'answers':
                $query->orderByDesc('answer_count');
                break;
            case 'resolved':
                $query->where('is_resolved', true)->orderByDesc('created_at');
                break;
            default:
                $query->orderByDesc('created_at');
                break;
        }

        $perPage = min((int) ($request->per_page ?? 20), 50);

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($perPage),
        ]);
    }

    /**
     * GET /api/qa/{id}
     * Single question with answers sorted by likes, best answer on top
     */
    public function show($id)
    {
        $question = QaQuestion::with([
            'user:id,nickname,nickname,avatar,title',
            'answers' => function ($q) {
                $q->with('user:id,nickname,nickname,avatar,title,total_accepted')
                  ->orderByDesc('is_accepted')
                  ->orderByDesc('like_count')
                  ->orderBy('created_at');
            },
            'acceptedAnswer.user:id,nickname,nickname,avatar,title',
        ])->findOrFail($id);

        $question->increment('view_count');

        // User recommendation status
        $question->is_recommended = false;
        if (Auth::check()) {
            $question->is_recommended = QaQuestionRecommend::where('question_id', $id)
                ->where('user_id', Auth::id())
                ->exists();
        }

        return response()->json([
            'success' => true,
            'data'    => $question,
        ]);
    }

    /**
     * POST /api/qa
     * Create question, optionally set bounty_points (deduct from user)
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'         => 'required|string|max:255',
            'content'       => 'required|string',
            'category_slug' => 'required|string',
            'point_reward'  => 'nullable|integer|min:0',
        ]);

        // Validate category slug
        $validSlugs = array_column($this->categoryList, 'slug');
        if (!in_array($request->category_slug, $validSlugs)) {
            return response()->json(['success' => false, 'message' => '유효하지 않은 카테고리입니다.'], 422);
        }

        $user = Auth::user();
        $pointReward = $request->point_reward ?? 0;

        // Deduct bounty points
        if ($pointReward > 0) {
            if ($user->points < $pointReward) {
                return response()->json(['success' => false, 'message' => '포인트가 부족합니다.'], 422);
            }
            $user->addPoints(-$pointReward, 'qa_question_reward', 'spend', null, "Q&A 질문 포인트 걸기: {$pointReward}P");
        }

        $question = QaQuestion::create([
            'user_id'       => $user->id,
            'category_slug' => $request->category_slug,
            'title'         => $request->title,
            'content'       => $request->content,
            'point_reward'  => $pointReward,
        ]);

        return response()->json([
            'success' => true,
            'message' => '질문이 등록되었습니다.',
            'data'    => $question->load('user:id,nickname,nickname,avatar'),
        ], 201);
    }

    /**
     * POST /api/qa/{id}/answer
     * Post answer to question
     */
    public function answer(Request $request, $id)
    {
        $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $question = QaQuestion::findOrFail($id);

        $answer = QaQuestionAnswer::create([
            'question_id' => $id,
            'user_id'     => Auth::id(),
            'content'     => $request->content,
        ]);

        $question->increment('answer_count');

        // Points for answering
        Auth::user()->addPoints(5, 'qa_answer', 'earn', $answer->id, 'Q&A 답변 작성');

        return response()->json([
            'success' => true,
            'message' => '답변이 등록되었습니다.',
            'data'    => $answer->load('user:id,nickname,nickname,avatar,title'),
        ], 201);
    }

    /**
     * POST /api/qa/answers/{answerId}/accept
     * Mark best answer (question author only), award bounty points to answerer
     */
    public function acceptAnswer($answerId)
    {
        $answer = QaQuestionAnswer::with('user')->findOrFail($answerId);
        $question = QaQuestion::findOrFail($answer->question_id);

        if ($question->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => '질문 작성자만 채택할 수 있습니다.'], 403);
        }

        if ($question->accepted_answer_id) {
            return response()->json(['success' => false, 'message' => '이미 채택된 답변이 있습니다.'], 422);
        }

        if ($answer->user_id === Auth::id()) {
            return response()->json(['success' => false, 'message' => '자신의 답변은 채택할 수 없습니다.'], 422);
        }

        DB::transaction(function () use ($answer, $question) {
            $answer->update(['is_accepted' => true]);

            $question->update([
                'accepted_answer_id' => $answer->id,
                'is_resolved'        => true,
                'resolved_at'        => now(),
            ]);

            // Award points: base 50 + bounty
            $rewardPoints = 50 + ($question->point_reward ?? 0);
            $answer->user->addPoints($rewardPoints, 'qa_accepted', 'earn', $answer->id, "Q&A 답변 채택 보상: {$rewardPoints}P");

            $answer->user->increment('total_accepted');

            // Update answerer's title
            $this->updateUserTitle($answer->user_id);
        });

        return response()->json([
            'success' => true,
            'message' => '답변이 채택되었습니다.',
            'data'    => $answer->fresh()->load('user:id,nickname,nickname,avatar,title'),
        ]);
    }

    /**
     * POST /api/qa/{id}/recommend
     * Toggle recommend (like) on a question
     */
    public function recommend($id)
    {
        $question = QaQuestion::findOrFail($id);

        $existing = QaQuestionRecommend::where('question_id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if ($existing) {
            $existing->delete();
            $question->decrement('recommend_count');
            return response()->json([
                'success' => true,
                'data'    => ['recommended' => false, 'recommend_count' => $question->fresh()->recommend_count],
            ]);
        }

        QaQuestionRecommend::create([
            'question_id' => $id,
            'user_id'     => Auth::id(),
        ]);
        $question->increment('recommend_count');

        return response()->json([
            'success' => true,
            'data'    => ['recommended' => true, 'recommend_count' => $question->fresh()->recommend_count],
        ]);
    }

    /**
     * POST /api/qa/answers/{answerId}/like
     * Toggle like on an answer
     */
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
            return response()->json([
                'success' => true,
                'data'    => ['liked' => false, 'like_count' => $answer->fresh()->like_count],
            ]);
        }

        DB::table('qa_question_answer_likes')->insert([
            'answer_id'  => $answerId,
            'user_id'    => Auth::id(),
            'created_at' => now(),
        ]);
        $answer->increment('like_count');

        return response()->json([
            'success' => true,
            'data'    => ['liked' => true, 'like_count' => $answer->fresh()->like_count],
        ]);
    }

    /**
     * PUT /api/qa/{id}
     */
    public function update(Request $request, $id)
    {
        $question = QaQuestion::findOrFail($id);

        if ($question->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => '수정 권한이 없습니다.'], 403);
        }

        $validated = $request->validate([
            'title'   => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
        ]);

        $question->update($validated);

        return response()->json([
            'success' => true,
            'message' => '수정되었습니다.',
            'data'    => $question,
        ]);
    }

    /**
     * DELETE /api/qa/{id}
     */
    public function destroy($id)
    {
        $question = QaQuestion::findOrFail($id);

        if ($question->user_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json(['success' => false, 'message' => '삭제 권한이 없습니다.'], 403);
        }

        $question->delete();

        return response()->json(['success' => true, 'message' => '삭제되었습니다.']);
    }

    /**
     * GET /api/qa/leaderboard
     */
    public function leaderboard(Request $request)
    {
        $period = $request->input('period', 'month');

        if ($period === 'month') {
            $leaders = User::select('users.id', 'users.nickname', 'users.nickname', 'users.avatar', 'users.title')
                ->selectRaw('COUNT(qa_question_answers.id) as monthly_accepted')
                ->join('qa_question_answers', 'users.id', '=', 'qa_question_answers.user_id')
                ->where('qa_question_answers.is_accepted', true)
                ->where('qa_question_answers.created_at', '>=', now()->startOfMonth())
                ->groupBy('users.id', 'users.nickname', 'users.nickname', 'users.avatar', 'users.title')
                ->orderByDesc('monthly_accepted')
                ->limit(10)
                ->get();
        } else {
            $leaders = User::select('id', 'nickname', 'nickname', 'avatar', 'title', 'total_accepted')
                ->where('total_accepted', '>', 0)
                ->orderByDesc('total_accepted')
                ->limit(10)
                ->get();
        }

        return response()->json(['success' => true, 'data' => $leaders]);
    }

    /**
     * Update user title based on accepted answer count
     */
    private function updateUserTitle(int $userId): void
    {
        $user = User::find($userId);
        if (!$user) return;

        $newTitle = '새싹 답변자';
        foreach ($this->titleLevels as $t) {
            if ($user->total_accepted >= $t['min_accepted']) {
                $newTitle = $t['title'];
            }
        }

        $user->update(['title' => $newTitle]);
    }
}
