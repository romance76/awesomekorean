<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RecipePost;
use App\Services\AdminCollectService;
use App\Services\RecipeService;
use Illuminate\Http\Request;

class AdminRecipeController extends Controller
{
    public function __construct(private RecipeService $recipeService, private AdminCollectService $collect) {}

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

    // POST /api/admin/recipes/sync — 범위 동기화 (큐 작업으로 넘기고 바로 응답. 결과는 GET /api/admin/collect-status 의 recipes)
    public function sync(Request $request)
    {
        $start = (int) ($request->start ?? 1);
        $end   = (int) ($request->end ?? 100);
        if ($start < 1 || $end < $start) {
            return response()->json(['success' => false, 'message' => '번호 범위를 확인해 주세요. 시작은 1 이상, 끝은 시작보다 크거나 같아야 해요.'], 422);
        }
        if ($end - $start >= 1000) {
            return response()->json(['success' => false, 'message' => '한 번에 최대 1000개까지만 가져올 수 있어요. 범위를 줄여 주세요.'], 422);
        }
        return $this->collect->begin('recipes', ['start' => $start, 'end' => $end], $request->user()?->id);
    }

    // POST /api/admin/recipes/sync-all — 전체 1~1000 (큐 작업으로 넘기고 바로 응답)
    public function syncAll(Request $request)
    {
        return $this->collect->begin('recipes', ['all' => true], $request->user()?->id);
    }

    // POST /api/admin/recipes/clear-all — 기존 전체 삭제
    public function clearAll()
    {
        // 전체 삭제는 막는다: 식품안전나라 수집 레시피는 평점·즐겨찾기까지 함께 지워져 복구가 어렵다
        return response()->json(['success' => false, 'code' => 'collected_data', 'message' => '레시피 전체 삭제는 지원하지 않아요. 필요하면 개별로 숨김 처리해 주세요.'], 422);
        $count = RecipePost::count();
        // TRUNCATE는 recipe_favorites/recipe_ratings가 외래키로 참조하고 있어 MySQL이
        // 항상 거부(500)했음 — 일반 DELETE는 두 FK 모두 CASCADE라 함께 정리됨
        RecipePost::query()->delete();
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
        $r = RecipePost::findOrFail($id);
        if (!empty($r->ext_id)) {
            return response()->json(['success' => false, 'code' => 'collected_data', 'message' => '수집된 레시피는 삭제할 수 없어요. 숨김을 사용해 주세요.'], 422);
        }
        $r->delete();
        return response()->json(['success' => true]);
    }

    // DELETE /api/admin/recipes/bulk-delete
    public function bulkDelete(Request $request)
    {
        $ids = array_slice(array_filter((array) $request->ids, 'is_numeric'), 0, 200);
        // 직접 쓴 레시피만 지운다 (수집분 ext_id 가 있는 것은 제외)
        $count = RecipePost::whereIn('id', $ids)->whereNull('ext_id')->delete();
        return response()->json(['success' => true, 'deleted' => $count]);
    }
}
