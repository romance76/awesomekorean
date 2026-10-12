<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MoneyCafe;
use App\Models\MoneyCategory;
use App\Models\MoneyGuide;
use App\Models\MoneyPost;
use App\Services\BadWordFilter;
use App\Support\HtmlSanitizer;
use App\Support\WritePoints;
use App\Traits\AdminAuthorizes;
use Illuminate\Http\Request;

/**
 * MONEY 허브 (qa/money_hub_plan.md) — 정보 공유 카페(가입 없이 보기·답글) · 카페 글 · 머니 가이드(설명 페이지).
 * 보기는 누구나, 새 글·수정·삭제는 로그인(수정·삭제는 글쓴이 또는 관리자).
 */
class MoneyController extends Controller
{
    use AdminAuthorizes;

    // 허브 첫 화면용: 카페 목록(최근 7일 새 글 수 포함) + 공개된 가이드
    public function hub()
    {
        $since = now()->subDays(7);
        $cafes = MoneyCafe::where('is_active', true)->orderBy('sort_order')
            ->select(['id', 'slug', 'name', 'icon', 'summary', 'headline', 'theme', 'art', 'sort_order'])
            ->with(['categories' => fn ($q) => $q->where('is_active', true)->select(['id', 'cafe_id', 'name', 'icon', 'sort_order'])])
            ->withCount([
                'posts as post_count' => fn ($q) => $q->where('is_hidden', false),
                'posts as new_count' => fn ($q) => $q->where('is_hidden', false)->where('created_at', '>=', $since),
            ])
            ->get();
        $guides = MoneyGuide::where('is_published', true)->orderBy('sort_order')->get(['slug', 'icon', 'title', 'summary']);
        return response()->json(['success' => true, 'data' => ['cafes' => $cafes, 'guides' => $guides]]);
    }

    // 카페 전체 안내 페이지: 카페마다 큰 문구·그림·주제
    public function cafes()
    {
        $cafes = MoneyCafe::where('is_active', true)->orderBy('sort_order')
            ->select(['id', 'slug', 'name', 'icon', 'summary', 'headline', 'theme', 'art', 'sort_order'])
            ->with(['categories' => fn ($q) => $q->where('is_active', true)->select(['id', 'cafe_id', 'name', 'icon', 'sort_order'])])
            ->withCount(['posts as post_count' => fn ($q) => $q->where('is_hidden', false)])
            ->get();
        return response()->json(['success' => true, 'data' => $cafes]);
    }

    // 카페 하나: 대문(큰 문구·그림·처음 방문 3단계·주제 카드·이 카페 가이드) + 인기 정보(조회 많은 글 5개)
    public function cafe($slug)
    {
        $cafe = MoneyCafe::where('slug', $slug)->where('is_active', true)
            ->firstOrFail(['id', 'slug', 'name', 'icon', 'summary', 'headline', 'theme', 'art', 'first_steps', 'intro', 'manager_user_id']);
        $categories = MoneyCategory::where('cafe_id', $cafe->id)->where('is_active', true)->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'name', 'icon', 'hint']);
        $counts = MoneyPost::where('cafe_id', $cafe->id)->where('is_hidden', false)->whereNotNull('category_id')
            ->selectRaw('category_id, count(*) as n')->groupBy('category_id')->pluck('n', 'category_id');
        $categories->each(fn ($c) => $c->post_count = (int) ($counts[$c->id] ?? 0));
        $popular = MoneyPost::where('cafe_id', $cafe->id)->where('is_hidden', false)
            ->orderByDesc('view_count')->limit(5)->get(['id', 'title', 'kind', 'view_count', 'comment_count']);
        // 이 카페 가이드: 공개된 것은 링크, 아직 준비 중인 것은 제목만 (무엇이 올라올지 미리 보여 줌)
        $guides = MoneyGuide::where('cafe_id', $cafe->id)->orderBy('sort_order')->orderBy('id')->limit(6)
            ->get(['slug', 'icon', 'title', 'summary', 'is_published'])
            ->map(fn ($g) => ['slug' => $g->is_published ? $g->slug : null, 'icon' => $g->icon, 'title' => $g->title, 'summary' => $g->summary, 'ready' => (bool) $g->is_published]);
        return response()->json(['success' => true, 'data' => ['cafe' => $cafe, 'categories' => $categories, 'popular' => $popular, 'guides' => $guides]]);
    }

    // 카페 게시판: 주제(category)·글 종류(kind)·검색·정렬(최신/조회/댓글). 고정 글(공지)은 항상 맨 위
    public function posts(Request $request, $slug)
    {
        $cafe = MoneyCafe::where('slug', $slug)->where('is_active', true)->firstOrFail(['id']);
        $sort = ['views' => 'view_count', 'comments' => 'comment_count'][$request->sort] ?? 'created_at';
        $q = MoneyPost::with('user:id,name,nickname,avatar,lifetime_points', 'category:id,name,icon')
            ->where('cafe_id', $cafe->id)->where('is_hidden', false)
            ->when(in_array($request->kind, MoneyPost::KINDS, true), fn ($x) => $x->where('kind', $request->kind))
            ->when((int) $request->category > 0, fn ($x) => $x->where('category_id', (int) $request->category))
            ->when($request->search, fn ($x, $v) => $x->where('title', 'like', '%' . addcslashes($v, '%_\\') . '%'))
            ->orderByDesc('is_pinned')->orderByDesc($sort)->orderByDesc('id');
        return response()->json(['success' => true, 'data' => $q->paginate(20, ['id', 'cafe_id', 'category_id', 'user_id', 'kind', 'title', 'is_pinned', 'view_count', 'comment_count', 'created_at'])]);
    }

    public function show($id)
    {
        $post = MoneyPost::with('user:id,name,nickname,avatar,lifetime_points', 'cafe:id,slug,name,icon,theme', 'category:id,name,icon')->where('is_hidden', false)->findOrFail($id);
        MoneyPost::whereKey($post->id)->increment('view_count');

        // 같은 카페(같은 주제가 있으면 그 주제 안)에서 글 목록과 같은 정렬(최신순)로 이전글·다음글을 찾는다.
        $base = fn () => MoneyPost::where('cafe_id', $post->cafe_id)->where('is_hidden', false)
            ->when($post->category_id, fn ($q) => $q->where('category_id', $post->category_id));
        $cols = ['id', 'title'];
        $prev = $base()->where(fn ($q) => $q->where('created_at', '>', $post->created_at)
                ->orWhere(fn ($q2) => $q2->where('created_at', $post->created_at)->where('id', '>', $post->id)))
            ->orderBy('created_at')->orderBy('id')->first($cols);
        $next = $base()->where(fn ($q) => $q->where('created_at', '<', $post->created_at)
                ->orWhere(fn ($q2) => $q2->where('created_at', $post->created_at)->where('id', '<', $post->id)))
            ->orderByDesc('created_at')->orderByDesc('id')->first($cols);

        $data = $post->toArray();
        $data['prev_post'] = $prev;
        $data['next_post'] = $next;
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($err = $this->badWords($data)) return $err;
        $cafe = MoneyCafe::where('slug', $request->cafe)->where('is_active', true)->firstOrFail(['id']);
        $post = MoneyPost::create([
            'cafe_id' => $cafe->id, 'category_id' => $this->categoryFor($cafe->id, $request->category_id),
            'user_id' => auth()->id(), 'kind' => $data['kind'],
            'title' => trim($data['title']), 'content' => HtmlSanitizer::clean($data['content']),
        ]);
        WritePoints::awardLater(auth()->user(), MoneyPost::class, $post->id, '머니 카페 글 작성');
        return response()->json(['success' => true, 'data' => $post], 201);
    }

    public function update(Request $request, $id)
    {
        $post = $this->findOwnedOrAdmin(MoneyPost::class, $id);
        $data = $this->validated($request, false);
        if ($err = $this->badWords($data)) return $err;
        $post->update(['kind' => $data['kind'], 'category_id' => $this->categoryFor($post->cafe_id, $request->category_id),
            'title' => trim($data['title']), 'content' => HtmlSanitizer::clean($data['content'])]);
        return response()->json(['success' => true, 'data' => $post]);
    }

    // 지우지 않고 숨긴다 (관리자 화면에서 복구 가능)
    public function destroy($id)
    {
        $post = $this->findOwnedOrAdmin(MoneyPost::class, $id);
        $post->forceFill(['is_hidden' => true])->save();
        return response()->json(['success' => true, 'message' => '글을 내렸어요']);
    }

    public function guides()
    {
        return response()->json(['success' => true, 'data' => MoneyGuide::where('is_published', true)->orderBy('sort_order')->get(['slug', 'icon', 'title', 'summary'])]);
    }

    public function guide($slug)
    {
        $g = MoneyGuide::where('slug', $slug)->where('is_published', true)->firstOrFail(['slug', 'icon', 'title', 'summary', 'content', 'blocks', 'updated_at']);
        return response()->json(['success' => true, 'data' => $g]);
    }

    private function validated(Request $request, bool $withCafe = true): array
    {
        $rules = [
            'kind' => 'required|in:' . implode(',', MoneyPost::KINDS),
            'title' => 'required|string|max:200',
            'content' => 'required|string|max:60000',
        ];
        if ($withCafe) $rules['cafe'] = 'required|string|max:60';
        return $request->validate($rules, [
            'kind.required' => '글 종류(정보·질문·소통)를 골라 주세요.', 'kind.in' => '글 종류(정보·질문·소통)를 골라 주세요.',
            'title.required' => '제목을 적어 주세요.', 'title.max' => '제목은 200자까지예요.',
            'content.required' => '내용을 적어 주세요.', 'content.max' => '내용이 너무 길어요.',
            'cafe.required' => '어느 카페에 올릴지 골라 주세요.',
        ]);
    }

    // 고른 주제가 그 카페의 켜진 주제일 때만 저장 (아니면 '주제 없음')
    private function categoryFor(int $cafeId, $categoryId): ?int
    {
        $id = (int) $categoryId;
        if ($id <= 0) return null;
        return MoneyCategory::where('id', $id)->where('cafe_id', $cafeId)->where('is_active', true)->exists() ? $id : null;
    }

    private function badWords(array $data)
    {
        if (BadWordFilter::contains($data['title'] . ' ' . strip_tags($data['content']))) {
            return response()->json(['success' => false, 'message' => '부적절한 표현이 포함되어 있어 올릴 수 없어요.'], 422);
        }
        return null;
    }
}
