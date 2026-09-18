<?php

namespace App\Console\Commands;

use App\Services\RecipeService;
use Illuminate\Console\Command;

/**
 * 식품안전나라 레시피 API에서 1~1000번 전체를 동기화.
 * 기존에는 관리자 화면(AdminRecipeController::syncAll)에서 수동으로만
 * 실행 가능했고 스케줄러에 등록돼 있지 않아 자동 수집이 전혀 안 되고 있었음.
 */
class SyncAllRecipes extends Command
{
    protected $signature   = 'recipes:sync-all {--end=1000 : 마지막 번호 (관리자 수동 실행 시 응답 지연 방지용으로 줄여서 호출 가능)}';
    protected $description = '식품안전나라 레시피 API 전체(1~1000) 동기화';

    public function handle(RecipeService $recipeService): int
    {
        $totalSaved = 0;
        $totalSkipped = 0;
        $end = max(100, (int) $this->option('end'));

        for ($i = 1; $i <= $end; $i += 100) {
            $r = $recipeService->syncFromApi($i, $i + 99);
            if (!empty($r['success'])) {
                $saved = (int) ($r['saved'] ?? 0);
                $skipped = (int) ($r['skipped'] ?? 0);
                $totalSaved += $saved;
                $totalSkipped += $skipped;
                $this->info("{$i}~" . ($i + 99) . ": 저장={$saved} 스킵={$skipped}");
            } else {
                $this->warn("{$i}~" . ($i + 99) . ": 실패 - " . ($r['message'] ?? '알 수 없는 오류'));
            }
        }

        $this->info("완료: 총 저장={$totalSaved} 스킵={$totalSkipped}");
        return self::SUCCESS;
    }
}
