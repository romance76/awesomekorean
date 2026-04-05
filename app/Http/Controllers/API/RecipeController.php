<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\RecipePost;
use App\Models\RecipeCategory;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    public function index(Request $request)
    {
        $query = RecipePost::with('user:id,name,nickname', 'category:id,name')
            ->when($request->category_id, fn($q,$v) => $q->where('category_id', $v))
            ->when($request->difficulty, fn($q,$v) => $q->where('difficulty', $v))
            ->when($request->search, fn($q,$v) => $q->where('title', 'like', "%{$v}%"))
            ->orderByDesc('created_at');
        return response()->json(['success' => true, 'data' => $query->paginate(20)]);
    }

    public function show($id)
    {
        $recipe = RecipePost::with('user:id,name,nickname,avatar', 'category:id,name')->findOrFail($id);
        $recipe->increment('view_count');
        return response()->json(['success' => true, 'data' => $recipe]);
    }

    public function store(Request $request)
    {
        $request->validate(['title' => 'required|max:200', 'content' => 'required']);
        $images = [];
        if ($request->hasFile('images')) foreach ($request->file('images') as $img) $images[] = $img->store('recipes', 'public');

        $recipe = RecipePost::create(array_merge(
            $request->only('title','title_ko','content','content_ko','ingredients','ingredients_ko','steps','steps_ko','category_id','servings','prep_time','cook_time','difficulty'),
            ['user_id' => auth()->id(), 'images' => $images ?: null]
        ));
        return response()->json(['success' => true, 'data' => $recipe], 201);
    }

    public function categories() { return response()->json(['success' => true, 'data' => RecipeCategory::orderBy('sort_order')->get()]); }

    public function destroy($id)
    {
        RecipePost::where('user_id', auth()->id())->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
}
