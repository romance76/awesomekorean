<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RecipePost;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    // GET /api/recipes — 공용 목록
    public function index(Request $request)
    {
        $query = RecipePost::where('is_active', true);

        if ($request->search) {
            $query->where('title', 'like', "%{$request->search}%");
        }
        if ($request->category) {
            $query->where('category', $request->category);
        }

        $sort = $request->sort ?? 'latest';
        if ($sort === 'views') {
            $query->orderByDesc('view_count');
        } else {
            $query->orderByDesc('id');
        }

        $perPage = (int) ($request->per_page ?? 20);
        return response()->json([
            'success' => true,
            'data' => $query->paginate($perPage),
        ]);
    }

    // GET /api/recipes/{id}
    public function show($id)
    {
        $recipe = RecipePost::where('is_active', true)->findOrFail($id);
        $recipe->increment('view_count');
        return response()->json(['success' => true, 'data' => $recipe]);
    }

    // GET /api/recipes/categories — 카테고리 목록 (count 포함)
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
}
