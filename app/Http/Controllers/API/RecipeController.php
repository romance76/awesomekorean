<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RecipeCategory;
use App\Models\RecipePost;
use App\Models\RecipeComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RecipeController extends Controller
{
    /**
     * GET /api/recipes/categories
     * List recipe categories
     */
    public function categories()
    {
        $cats = RecipeCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Prefer Korean name
        $cats->transform(function ($cat) {
            if ($cat->name_ko) {
                $cat->name = $cat->name_ko;
            }
            return $cat;
        });

        return response()->json(['success' => true, 'data' => $cats]);
    }

    /**
     * GET /api/recipes
     * List recipes with category, difficulty, search, sort, pagination
     */
    public function index(Request $request)
    {
        $query = RecipePost::with('user:id,nickname,nickname,avatar', 'category:id,name,name_ko,key,icon')
            ->where('is_hidden', false);

        // Category filter
        if ($request->filled('category')) {
            $query->whereHas('category', fn($q) => $q->where('key', $request->category));
        }

        // Difficulty filter
        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        // Search
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('title_ko', 'like', "%{$s}%")
                  ->orWhere('intro', 'like', "%{$s}%")
                  ->orWhere('intro_ko', 'like', "%{$s}%")
                  ->orWhereJsonContains('tags', $s);
            });
        }

        // Sort
        $sort = $request->input('sort', 'newest');
        switch ($sort) {
            case 'popular':
                $query->orderByDesc('like_count');
                break;
            case 'views':
                $query->orderByDesc('view_count');
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
     * GET /api/recipes/{id}
     * Single recipe with view_count increment, author info
     */
    public function show($id)
    {
        $recipe = RecipePost::with([
            'user:id,nickname,nickname,avatar',
            'category:id,name,name_ko,key,icon',
            'comments' => fn($q) => $q->with('user:id,nickname,nickname,avatar')->latest()->limit(20),
        ])->findOrFail($id);

        $recipe->increment('view_count');

        // Like / bookmark status
        $isLiked = false;
        $isBookmarked = false;
        if (Auth::check()) {
            $isLiked = DB::table('content_likes')
                ->where('user_id', Auth::id())
                ->where('likeable_type', 'recipe')
                ->where('likeable_id', $id)
                ->exists();
            $isBookmarked = DB::table('content_likes')
                ->where('user_id', Auth::id())
                ->where('likeable_type', 'recipe_bookmark')
                ->where('likeable_id', $id)
                ->exists();
        }

        // Related recipes
        $related = RecipePost::where('category_id', $recipe->category_id)
            ->where('id', '!=', $id)
            ->where('is_hidden', false)
            ->inRandomOrder()
            ->limit(6)
            ->get(['id', 'title', 'title_ko', 'image_url', 'difficulty', 'cook_time', 'like_count', 'view_count', 'category_id']);

        // Average rating
        $avgRating = DB::table('recipe_comments')
            ->where('recipe_id', $id)
            ->whereNotNull('rating')
            ->avg('rating');

        // Prefer Korean category name
        if ($recipe->category && $recipe->category->name_ko) {
            $recipe->category->name = $recipe->category->name_ko;
        }

        $data = $recipe->toArray();

        // Korean-first display fields
        $data['display_title']       = $recipe->title_ko ?: $recipe->title;
        $data['display_intro']       = $recipe->intro_ko ?: $recipe->intro;
        $data['display_ingredients'] = $recipe->ingredients_ko ?: $recipe->ingredients;
        $data['display_steps']       = $recipe->steps_ko ?: $recipe->steps;
        $data['display_tips']        = $recipe->tips_ko ?: $recipe->tips;

        return response()->json([
            'success' => true,
            'data'    => array_merge($data, [
                'is_liked'      => $isLiked,
                'is_bookmarked' => $isBookmarked,
                'related'       => $related,
                'avg_rating'    => $avgRating ? round($avgRating, 1) : null,
                'comment_count' => $recipe->comments->count(),
            ]),
        ]);
    }

    /**
     * POST /api/recipes
     * Create recipe with Korean+English fields, image upload
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:200',
            'category_id' => 'required|exists:recipe_categories,id',
            'ingredients' => 'nullable|array',
            'steps'       => 'nullable|array',
            'tips'        => 'nullable|array',
            'tags'        => 'nullable|array',
            'image'       => 'nullable|image|max:5120',
        ]);

        $imageUrl = $request->image_url;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('recipes', 'public');
            $imageUrl = asset('storage/' . $path);
        }

        $recipe = RecipePost::create([
            'user_id'        => Auth::id(),
            'title'          => $request->title,
            'title_ko'       => $request->title_ko,
            'intro'          => $request->intro,
            'intro_ko'       => $request->intro_ko,
            'content'        => $request->content,
            'content_ko'     => $request->content_ko,
            'category_id'    => $request->category_id,
            'difficulty'     => $request->difficulty,
            'cook_time'      => $request->cook_time,
            'calories'       => $request->calories,
            'servings'       => $request->servings ?? 2,
            'ingredients'    => $request->ingredients ?? [],
            'ingredients_ko' => $request->ingredients_ko,
            'steps'          => $request->steps ?? [],
            'steps_ko'       => $request->steps_ko,
            'tips'           => $request->tips ?? [],
            'tips_ko'        => $request->tips_ko,
            'tags'           => $request->tags ?? [],
            'image_url'      => $imageUrl,
            'source'         => 'user',
        ]);

        // Points
        Auth::user()->addPoints(5, 'recipe_write', 'earn', $recipe->id, '레시피 작성');

        return response()->json([
            'success' => true,
            'message' => '레시피가 등록되었습니다.',
            'data'    => $recipe->load('user:id,nickname,avatar', 'category:id,name,key,icon'),
        ], 201);
    }

    /**
     * PUT /api/recipes/{id}
     * Update own recipe only
     */
    public function update(Request $request, $id)
    {
        $recipe = RecipePost::findOrFail($id);

        if ($recipe->user_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json(['success' => false, 'message' => '수정 권한이 없습니다.'], 403);
        }

        $request->validate([
            'title'       => 'sometimes|string|max:200',
            'category_id' => 'sometimes|exists:recipe_categories,id',
            'image'       => 'nullable|image|max:5120',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('recipes', 'public');
            $recipe->image_url = asset('storage/' . $path);
        }

        $recipe->fill($request->only([
            'title', 'title_ko', 'intro', 'intro_ko', 'content', 'content_ko',
            'category_id', 'difficulty', 'cook_time', 'calories', 'servings',
            'ingredients', 'ingredients_ko', 'steps', 'steps_ko',
            'tips', 'tips_ko', 'tags', 'image_url',
        ]));
        $recipe->save();

        return response()->json([
            'success' => true,
            'message' => '수정되었습니다.',
            'data'    => $recipe,
        ]);
    }

    /**
     * DELETE /api/recipes/{id}
     * Delete own recipe only
     */
    public function destroy($id)
    {
        $recipe = RecipePost::findOrFail($id);

        if ($recipe->user_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json(['success' => false, 'message' => '삭제 권한이 없습니다.'], 403);
        }

        $recipe->delete();

        return response()->json(['success' => true, 'message' => '삭제되었습니다.']);
    }

    /**
     * POST /api/recipes/{id}/like
     */
    public function like($id)
    {
        RecipePost::findOrFail($id);
        $userId = Auth::id();

        $existing = DB::table('content_likes')
            ->where('user_id', $userId)
            ->where('likeable_type', 'recipe')
            ->where('likeable_id', $id)
            ->first();

        if ($existing) {
            DB::table('content_likes')->where('id', $existing->id)->delete();
            RecipePost::where('id', $id)->decrement('like_count');
        } else {
            DB::table('content_likes')->insert([
                'user_id'       => $userId,
                'likeable_type' => 'recipe',
                'likeable_id'   => $id,
                'created_at'    => now(),
            ]);
            RecipePost::where('id', $id)->increment('like_count');
        }

        $count = DB::table('content_likes')
            ->where('likeable_type', 'recipe')
            ->where('likeable_id', $id)
            ->count();

        return response()->json([
            'success' => true,
            'data'    => ['liked' => !$existing, 'like_count' => $count],
        ]);
    }

    /**
     * POST /api/recipes/{id}/bookmark
     */
    public function bookmark($id)
    {
        RecipePost::findOrFail($id);
        $userId = Auth::id();

        $existing = DB::table('content_likes')
            ->where('user_id', $userId)
            ->where('likeable_type', 'recipe_bookmark')
            ->where('likeable_id', $id)
            ->first();

        if ($existing) {
            DB::table('content_likes')->where('id', $existing->id)->delete();
            RecipePost::where('id', $id)->decrement('bookmark_count');
        } else {
            DB::table('content_likes')->insert([
                'user_id'       => $userId,
                'likeable_type' => 'recipe_bookmark',
                'likeable_id'   => $id,
                'created_at'    => now(),
            ]);
            RecipePost::where('id', $id)->increment('bookmark_count');
        }

        return response()->json([
            'success' => true,
            'data'    => ['bookmarked' => !$existing],
        ]);
    }

    /**
     * POST /api/recipes/{id}/comment
     */
    public function comment(Request $request, $id)
    {
        RecipePost::findOrFail($id);
        $request->validate([
            'content' => 'required|string|max:1000',
            'rating'  => 'nullable|integer|min:1|max:5',
        ]);

        $comment = RecipeComment::create([
            'recipe_id' => $id,
            'user_id'   => Auth::id(),
            'content'   => $request->content,
            'rating'    => $request->rating,
        ]);

        return response()->json([
            'success' => true,
            'message' => '댓글이 등록되었습니다.',
            'data'    => $comment->load('user:id,nickname,nickname,avatar'),
        ], 201);
    }

    /**
     * POST /api/recipes/upload-image
     */
    public function uploadImage(Request $request)
    {
        $request->validate(['image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120']);
        $path = $request->file('image')->store('recipes', 'public');

        return response()->json([
            'success' => true,
            'data'    => ['url' => asset('storage/' . $path)],
        ]);
    }

    /**
     * GET /api/recipes/popular
     */
    public function popular()
    {
        $recipes = RecipePost::where('is_hidden', false)
            ->orderByDesc('like_count')
            ->limit(10)
            ->get(['id', 'title', 'title_ko', 'image_url', 'difficulty', 'cook_time', 'like_count']);

        return response()->json(['success' => true, 'data' => $recipes]);
    }

    /**
     * GET /api/recipes/my
     */
    public function myRecipes()
    {
        $recipes = RecipePost::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $recipes]);
    }
}
