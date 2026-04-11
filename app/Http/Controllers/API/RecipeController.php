<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RecipePost;
use App\Models\RecipeFavorite;
use App\Models\RecipeRating;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    // GET /api/recipes — 공용 목록
    public function index(Request $request)
    {
        $query = RecipePost::where('is_active', true);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%")
                  ->orWhere('title_en', 'like', "%{$request->search}%");
            });
        }
        if ($request->category) {
            $query->where('category', $request->category);
        }
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $sort = $request->sort ?? 'rating';
        if ($sort === 'popular' || $sort === 'views') {
            $query->orderByDesc('view_count')->orderByDesc('id');
        } elseif ($sort === 'latest') {
            $query->orderByDesc('id');
        } else {
            // 기본: 별점 높은 순 (평가 많은 것 우선) + ID desc
            $query->orderByDesc('rating_avg')->orderByDesc('rating_count')->orderByDesc('id');
        }

        $perPage = (int) ($request->per_page ?? 20);
        $paginated = $query->paginate($perPage);

        // 로그인 유저면 각 레시피에 is_favorited 주입
        if (auth()->check()) {
            $ids = collect($paginated->items())->pluck('id');
            $favIds = RecipeFavorite::where('user_id', auth()->id())
                ->whereIn('recipe_id', $ids)->pluck('recipe_id')->toArray();
            $paginated->getCollection()->transform(function ($r) use ($favIds) {
                $r->is_favorited = in_array($r->id, $favIds);
                return $r;
            });
        }

        return response()->json(['success' => true, 'data' => $paginated]);
    }

    // GET /api/recipes/{id}
    public function show($id)
    {
        $recipe = RecipePost::with('user:id,name,nickname,avatar')
            ->where('is_active', true)
            ->findOrFail($id);
        $recipe->increment('view_count');

        if (auth()->check()) {
            $recipe->is_favorited = RecipeFavorite::where('user_id', auth()->id())
                ->where('recipe_id', $id)->exists();
            $userRating = RecipeRating::where('user_id', auth()->id())
                ->where('recipe_id', $id)->first();
            $recipe->my_rating = $userRating?->rating;
        }

        return response()->json(['success' => true, 'data' => $recipe]);
    }

    // GET /api/recipes/categories
    public function categories()
    {
        $cats = RecipePost::where('is_active', true)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->groupBy('category')
            ->selectRaw('category, count(*) as count')
            ->orderByDesc('count')
            ->get();
        return response()->json(['success' => true, 'data' => $cats]);
    }

    // POST /api/recipes — 유저 레시피 생성
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:200',
            'title_en' => 'nullable|string|max:200',
            'category' => 'nullable|string|max:50',
            'cook_method' => 'nullable|string|max:50',
            'ingredients' => 'nullable|string',
            'ingredients_en' => 'nullable|string',
            'ingredients_structured' => 'nullable',
            'servings' => 'nullable|string|max:50',
            'steps' => 'nullable',
            'thumbnail_file' => 'nullable|image|max:10240',
            'hash_tags' => 'nullable|string|max:300',
        ]);

        $thumbnailUrl = null;
        if ($request->hasFile('thumbnail_file')) {
            $path = $request->file('thumbnail_file')->store('recipes', 'public');
            $thumbnailUrl = '/storage/' . $path;
        }

        // steps 가 JSON 문자열로 올 수도 있음
        $steps = $request->steps;
        if (is_string($steps)) {
            $decoded = json_decode($steps, true);
            if (is_array($decoded)) $steps = $decoded;
        }
        if (!is_array($steps)) $steps = [];

        // ingredients_structured
        $structured = $request->ingredients_structured;
        if (is_string($structured)) {
            $decoded = json_decode($structured, true);
            if (is_array($decoded)) $structured = $decoded;
        }
        if (!is_array($structured)) $structured = null;

        $recipe = RecipePost::create([
            'user_id' => auth()->id(),
            'source' => 'user',
            'title' => $request->title,
            'title_en' => $request->title_en,
            'category' => $request->category ?: '기타',
            'cook_method' => $request->cook_method,
            'ingredients' => $request->ingredients,
            'ingredients_en' => $request->ingredients_en,
            'ingredients_structured' => $structured,
            'servings' => $request->servings,
            'steps' => $steps,
            'thumbnail' => $thumbnailUrl,
            'hash_tags' => $request->hash_tags,
            'is_active' => true,
        ]);

        return response()->json(['success' => true, 'data' => $recipe], 201);
    }

    // PUT /api/recipes/{id} — 본인 레시피 수정
    public function update(Request $request, $id)
    {
        $recipe = RecipePost::where('user_id', auth()->id())->findOrFail($id);
        $request->validate([
            'title' => 'nullable|string|max:200',
            'title_en' => 'nullable|string|max:200',
            'category' => 'nullable|string|max:50',
            'cook_method' => 'nullable|string|max:50',
            'ingredients' => 'nullable|string',
            'ingredients_en' => 'nullable|string',
            'servings' => 'nullable|string|max:50',
            'thumbnail_file' => 'nullable|image|max:10240',
        ]);

        $data = $request->only([
            'title', 'title_en', 'category', 'cook_method',
            'ingredients', 'ingredients_en', 'servings', 'hash_tags',
        ]);

        if ($request->hasFile('thumbnail_file')) {
            $path = $request->file('thumbnail_file')->store('recipes', 'public');
            $data['thumbnail'] = '/storage/' . $path;
        }

        $steps = $request->steps;
        if (is_string($steps)) {
            $decoded = json_decode($steps, true);
            if (is_array($decoded)) $steps = $decoded;
        }
        if (is_array($steps)) $data['steps'] = $steps;

        $structured = $request->ingredients_structured;
        if (is_string($structured)) {
            $decoded = json_decode($structured, true);
            if (is_array($decoded)) $structured = $decoded;
        }
        if (is_array($structured)) $data['ingredients_structured'] = $structured;

        $recipe->update($data);
        return response()->json(['success' => true, 'data' => $recipe->fresh()]);
    }

    // DELETE /api/recipes/{id} — 본인 레시피 삭제
    public function destroy($id)
    {
        $recipe = RecipePost::where('user_id', auth()->id())->findOrFail($id);
        $recipe->delete();
        return response()->json(['success' => true]);
    }

    // POST /api/recipes/{id}/rate — 별점 주기 (댓글 선택)
    public function rate(Request $request, $id)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $recipe = RecipePost::findOrFail($id);

        RecipeRating::updateOrCreate(
            ['user_id' => auth()->id(), 'recipe_id' => $id],
            ['rating' => $request->rating, 'comment' => $request->comment]
        );

        $recipe->recomputeRating();

        return response()->json([
            'success' => true,
            'rating_avg' => $recipe->fresh()->rating_avg,
            'rating_count' => $recipe->fresh()->rating_count,
            'my_rating' => $request->rating,
        ]);
    }

    // GET /api/recipes/{id}/comments — 댓글(평점+리뷰) 리스트
    public function comments($id)
    {
        $comments = RecipeRating::with('user:id,name,nickname,avatar')
            ->where('recipe_id', $id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $comments]);
    }

    // DELETE /api/recipes/{id}/comments/{commentId} — 본인 댓글 삭제
    public function deleteComment($id, $commentId)
    {
        $comment = RecipeRating::where('user_id', auth()->id())
            ->where('recipe_id', $id)
            ->findOrFail($commentId);
        $comment->delete();

        $recipe = RecipePost::find($id);
        if ($recipe) $recipe->recomputeRating();

        return response()->json(['success' => true]);
    }

    // POST /api/recipes/{id}/favorite — 찜 토글
    public function toggleFavorite($id)
    {
        $recipe = RecipePost::findOrFail($id);
        $existing = RecipeFavorite::where('user_id', auth()->id())
            ->where('recipe_id', $id)->first();

        if ($existing) {
            $existing->delete();
            $recipe->recomputeFavoriteCount();
            return response()->json([
                'success' => true,
                'is_favorited' => false,
                'favorite_count' => $recipe->fresh()->favorite_count,
            ]);
        }

        RecipeFavorite::create(['user_id' => auth()->id(), 'recipe_id' => $id]);
        $recipe->recomputeFavoriteCount();
        return response()->json([
            'success' => true,
            'is_favorited' => true,
            'favorite_count' => $recipe->fresh()->favorite_count,
        ]);
    }

    // GET /api/recipes/my/favorites — 내 찜 목록
    public function myFavorites(Request $request)
    {
        $favIds = RecipeFavorite::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->pluck('recipe_id');

        $recipes = RecipePost::whereIn('id', $favIds)
            ->where('is_active', true)
            ->paginate((int) ($request->per_page ?? 20));

        $recipes->getCollection()->transform(function ($r) {
            $r->is_favorited = true;
            return $r;
        });

        return response()->json(['success' => true, 'data' => $recipes]);
    }
}
