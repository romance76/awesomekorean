<?php

namespace App\Services;

use App\Models\RecipePost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecipeService
{
    private string $apiKey;
    private string $baseUrl;
    private string $service;

    public function __construct()
    {
        $this->apiKey  = config('services.foodsafety.api_key');
        $this->baseUrl = rtrim(config('services.foodsafety.url'), '/');
        $this->service = config('services.foodsafety.service', 'COOKRCP01');
    }

    /**
     * 식품안전나라 API 에서 레시피 가져와서 DB 저장
     */
    public function syncFromApi(int $start = 1, int $end = 100): array
    {
        $url = "{$this->baseUrl}/{$this->apiKey}/{$this->service}/json/{$start}/{$end}";

        try {
            $response = Http::timeout(30)->get($url);
            if (!$response->ok()) {
                return [
                    'success' => false,
                    'error'   => 'HTTP ' . $response->status(),
                    'saved'   => 0,
                    'skipped' => 0,
                    'total'   => 0,
                ];
            }

            $data = $response->json();
            $rows = $data[$this->service]['row'] ?? [];

            $saved = 0;
            $skipped = 0;

            foreach ($rows as $row) {
                $extId = $row['RCP_SEQ'] ?? null;
                if (!$extId) continue;

                if (RecipePost::where('ext_id', $extId)->exists()) {
                    $skipped++;
                    continue;
                }

                $steps = $this->parseSteps($row);

                RecipePost::create([
                    'source'      => 'foodsafety',
                    'ext_id'      => $extId,
                    'title'       => $row['RCP_NM'] ?? '',
                    'category'    => $row['RCP_PAT2'] ?? null,
                    'cook_method' => $row['RCP_WAY2'] ?? null,
                    'ingredients' => $row['RCP_PARTS_DTLS'] ?? null,
                    'calories'    => $row['INFO_ENG'] ?? null,
                    'carbs'       => $row['INFO_CAR'] ?? null,
                    'protein'     => $row['INFO_PRO'] ?? null,
                    'fat'         => $row['INFO_FAT'] ?? null,
                    'sodium'      => $row['INFO_NA'] ?? null,
                    'steps'       => $steps,
                    'thumbnail'   => $row['ATT_FILE_NO_MAIN'] ?? null,
                    'hash_tags'   => $row['HASH_TAG'] ?? null,
                    'is_active'   => true,
                ]);
                $saved++;
            }

            return [
                'success' => true,
                'saved'   => $saved,
                'skipped' => $skipped,
                'total'   => count($rows),
            ];
        } catch (\Exception $e) {
            Log::error('Recipe API sync failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error'   => $e->getMessage(),
                'saved'   => 0,
                'skipped' => 0,
                'total'   => 0,
            ];
        }
    }

    /**
     * MANUAL01..20 + MANUAL_IMG01..20 을 steps[] 로 변환
     */
    private function parseSteps(array $row): array
    {
        $steps = [];
        for ($i = 1; $i <= 20; $i++) {
            $suffix = str_pad($i, 2, '0', STR_PAD_LEFT);
            $text   = trim($row['MANUAL' . $suffix] ?? '');
            $img    = trim($row['MANUAL_IMG' . $suffix] ?? '');
            if ($text === '') break;
            // 끝에 불필요한 알파벳 1자 제거 (a, b, c 등 — API 데이터 오류)
            $text = preg_replace('/[a-zA-Z]$/', '', $text);
            // 앞에 "1. ", "2. " 등 중복 번호 제거
            $text = preg_replace('/^\d+\.\s*/', '', $text);
            $text = trim($text);
            if ($text === '') continue;
            $steps[] = [
                'order'     => $i,
                'text'      => $text,
                'image_url' => $img !== '' ? $img : null,
            ];
        }
        return $steps;
    }

    /**
     * API 연결 상태 확인 (총 레시피 수 조회)
     */
    public function testConnection(): array
    {
        try {
            $url = "{$this->baseUrl}/{$this->apiKey}/{$this->service}/json/1/1";
            $response = Http::timeout(10)->get($url);
            $data = $response->json();
            $total = $data[$this->service]['total_count'] ?? 0;
            return [
                'success' => true,
                'total'   => (int) $total,
                'status'  => $response->status(),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
