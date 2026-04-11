<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RecipePost;
use App\Services\RecipeService;
use Illuminate\Http\Request;

class AdminRecipeController extends Controller
{
    public function __construct(private RecipeService $recipeService) {}

    // GET /api/admin/recipes — 관리자 목록 (전체 상태)
    public function index(Request $request)
    {
        $query = RecipePost::query();
        if ($request->search)   $query->where('title', 'like', "%{$request->search}%");
        if ($request->category) $query->where('category', $request->category);
        if ($request->status !== null && $request->status !== '') {
            $query->where('is_active', (bool) $request->status);
        }
        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('id')->paginate(20),
        ]);
    }

    // GET /api/admin/recipes/stats
    public function stats()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total'    => RecipePost::count(),
                'active'   => RecipePost::where('is_active', true)->count(),
                'inactive' => RecipePost::where('is_active', false)->count(),
                'categories' => RecipePost::whereNotNull('category')
                    ->where('category', '!=', '')
                    ->groupBy('category')
                    ->selectRaw('category, count(*) as count')
                    ->pluck('count', 'category'),
            ],
        ]);
    }

    // GET /api/admin/recipes/test-connection
    public function testConnection()
    {
        return response()->json($this->recipeService->testConnection());
    }

    // POST /api/admin/recipes/sync — 범위 동기화
    public function sync(Request $request)
    {
        $start  = (int) ($request->start ?? 1);
        $end    = (int) ($request->end ?? 100);
        $result = $this->recipeService->syncFromApi($start, $end);
        return response()->json($result);
    }

    // POST /api/admin/recipes/sync-all — 전체 1~1000
    public function syncAll()
    {
        $totalSaved = 0;
        $totalSkipped = 0;
        for ($i = 1; $i <= 1000; $i += 100) {
            $r = $this->recipeService->syncFromApi($i, $i + 99);
            if (!empty($r['success'])) {
                $totalSaved += (int) ($r['saved'] ?? 0);
                $totalSkipped += (int) ($r['skipped'] ?? 0);
            }
        }
        return response()->json([
            'success'     => true,
            'total_saved' => $totalSaved,
            'skipped'     => $totalSkipped,
        ]);
    }

    // POST /api/admin/recipes/clear-all — 기존 전체 삭제
    public function clearAll()
    {
        $count = RecipePost::count();
        RecipePost::truncate();
        return response()->json(['success' => true, 'deleted' => $count]);
    }

    // PATCH /api/admin/recipes/{id}/toggle
    public function toggle($id)
    {
        $recipe = RecipePost::findOrFail($id);
        $recipe->update(['is_active' => !$recipe->is_active]);
        return response()->json(['success' => true, 'is_active' => $recipe->is_active]);
    }

    // PUT /api/admin/recipes/{id}
    public function update(Request $request, $id)
    {
        $recipe = RecipePost::findOrFail($id);
        $recipe->update($request->only([
            'title', 'category', 'cook_method',
            'ingredients', 'calories', 'carbs', 'protein', 'fat', 'sodium',
            'thumbnail', 'hash_tags', 'is_active',
        ]));
        return response()->json(['success' => true, 'data' => $recipe->fresh()]);
    }

    // DELETE /api/admin/recipes/{id}
    public function destroy($id)
    {
        RecipePost::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // DELETE /api/admin/recipes/bulk-delete
    public function bulkDelete(Request $request)
    {
        $ids = (array) $request->ids;
        $count = RecipePost::whereIn('id', $ids)->delete();
        return response()->json(['success' => true, 'deleted' => $count]);
    }
}
