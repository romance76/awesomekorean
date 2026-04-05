<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\RecipeCategory;
use App\Models\RecipePost;
use App\Models\RecipeComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller {
    public function categories() {
        $cats = RecipeCategory::where('is_active', true)->orderBy('sort_order')->get();
        $cats->transform(function ($cat) {
            if ($cat->name_ko) $cat->name = $cat->name_ko;
            return $cat;
        });
        return response()->json($cats);
    }

    public function index(Request $request) {
        $query = RecipePost::with('user:id,name,username', 'category:id,name,name_ko,key,icon')
            ->where('is_hidden', false)
            ->orderByDesc('created_at');
        if ($request->category) $query->whereHas('category', fn($q) => $q->where('key', $request->category));
        if ($request->difficulty) $query->where('difficulty', $request->difficulty);
        if ($request->search) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('title', 'like', "%$s%")
                  ->orWhere('title_ko', 'like', "%$s%")
                  ->orWhere('intro', 'like', "%$s%")
                  ->orWhere('intro_ko', 'like', "%$s%")
                  ->orWhereJsonContains('ingredients', $s)
                  ->orWhereJsonContains('tags', $s);
            });
        }
        return response()->json($query->paginate(20));
    }

    public function show($id) {
        $recipe = RecipePost::with(['user:id,name,username,avatar', 'category:id,name,name_ko,key,icon',
            'comments' => fn($q) => $q->with('user:id,name,username,avatar')->latest()->limit(20),
        ])->findOrFail($id);
        $recipe->increment('view_count');
        $isLiked = Auth::check() ? DB::table('content_likes')->where('user_id', Auth::id())->where('likeable_type', 'recipe')->where('likeable_id', $id)->exists() : false;
        $isBookmarked = Auth::check() ? DB::table('content_likes')->where('user_id', Auth::id())->where('likeable_type', 'recipe_bookmark')->where('likeable_id', $id)->exists() : false;

        if ($recipe->category && $recipe->category->name_ko) {
            $recipe->category->name = $recipe->category->name_ko;
        }

        $data = $recipe->toArray();
        $data['display_title'] = $recipe->title_ko ?: $recipe->title;
        $data['display_intro'] = $recipe->intro_ko ?: $recipe->intro;
        $data['display_ingredients'] = $recipe->ingredients_ko ?: $recipe->ingredients;
        $data['display_steps'] = $recipe->steps_ko ?: $recipe->steps;
        $data['display_tips'] = $recipe->tips_ko ?: $recipe->tips;

        return response()->json(array_merge($data, ['is_liked' => $isLiked, 'is_bookmarked' => $isBookmarked]));
    }

    public function store(Request $request) {
        $request->validate(['title' => 'required|string|max:200', 'category_id' => 'required|exists:recipe_categories,id']);
        $recipe = RecipePost::create([...$request->only(['title','intro','category_id','difficulty','cook_time','calories','servings','ingredients','steps','tips','tags','image_url']), 'user_id' => Auth::id()]);
        return response()->json($recipe, 201);
    }

    public function like($id) {
        RecipePost::findOrFail($id);
        $userId = Auth::id();
        $existing = DB::table('content_likes')->where('user_id', $userId)->where('likeable_type', 'recipe')->where('likeable_id', $id)->first();
        if ($existing) {
            DB::table('content_likes')->where('id', $existing->id)->delete();
            RecipePost::where('id', $id)->decrement('like_count');
        } else {
            DB::table('content_likes')->insert(['user_id' => $userId, 'likeable_type' => 'recipe', 'likeable_id' => $id, 'created_at' => now()]);
            RecipePost::where('id', $id)->increment('like_count');
        }
        $count = DB::table('content_likes')->where('likeable_type', 'recipe')->where('likeable_id', $id)->count();
        return response()->json(['liked' => !$existing, 'like_count' => $count]);
    }

    public function bookmark($id) {
        RecipePost::findOrFail($id);
        $userId = Auth::id();
        $existing = DB::table('content_likes')->where('user_id', $userId)->where('likeable_type', 'recipe_bookmark')->where('likeable_id', $id)->first();
        if ($existing) {
            DB::table('content_likes')->where('id', $existing->id)->delete();
            RecipePost::where('id', $id)->decrement('bookmark_count');
        } else {
            DB::table('content_likes')->insert(['user_id' => $userId, 'likeable_type' => 'recipe_bookmark', 'likeable_id' => $id, 'created_at' => now()]);
            RecipePost::where('id', $id)->increment('bookmark_count');
        }
        return response()->json(['bookmarked' => !$existing]);
    }

    public function comment(Request $request, $id) {
        RecipePost::findOrFail($id);
        $request->validate(['content' => 'required|string|max:1000', 'rating' => 'nullable|integer|min:1|max:5']);
        $comment = RecipeComment::create(['recipe_id' => $id, 'user_id' => Auth::id(), 'content' => $request->content, 'rating' => $request->rating]);
        return response()->json(['message' => '댓글 등록', 'comment' => $comment->load('user:id,name,username,avatar')], 201);
    }

    public function popular() {
        return response()->json(RecipePost::where('is_hidden', false)->orderByDesc('like_count')->limit(10)->get(['id','title','title_ko','image_url','difficulty','cook_time','like_count']));
    }
}
