<?php

namespace App\Console\Commands;

use App\Models\RecipePost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TranslateRecipes extends Command
{
    protected $signature = 'recipes:translate {--limit=50} {--all : 전체 번역} {--force : 이미 번역된 것도 재번역}';
    protected $description = '레시피 ingredients/steps 를 OpenAI gpt-4o-mini 로 한영 번역';

    public function handle()
    {
        $apiKey = config('services.openai.key') ?: env('OPENAI_API_KEY');
        if (!$apiKey) {
            $this->error('OPENAI_API_KEY 가 설정되지 않았습니다');
            return 1;
        }

        $query = RecipePost::query()->where('is_active', true);
        if (!$this->option('force')) {
            $query->whereNull('translated_at');
        }
        $limit = (int) $this->option('limit');
        if (!$this->option('all')) {
            $query->limit($limit);
        }

        $total = $query->count();
        $this->info("번역 대상: {$total}개");
        if (!$total) return 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $success = 0;
        $failed = 0;

        $query->chunkById(20, function ($recipes) use (&$success, &$failed, $bar, $apiKey) {
            foreach ($recipes as $recipe) {
                try {
                    $result = $this->translateRecipe($recipe, $apiKey);
                    if ($result) {
                        $recipe->update([
                            'ingredients_en' => $result['ingredients_en'] ?? null,
                            'ingredients_structured' => $result['ingredients_structured'] ?? null,
                            'title_en' => $result['title_en'] ?? $recipe->title_en,
                            'steps' => $result['steps'] ?? $recipe->steps,
                            'translated_at' => now(),
                        ]);
                        $success++;
                    } else {
                        $failed++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $this->newLine();
                    $this->warn("#{$recipe->id} 실패: " . $e->getMessage());
                }
                $bar->advance();
                usleep(200000); // 0.2s between calls
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ 성공: {$success} / ❌ 실패: {$failed}");
        return 0;
    }

    private function translateRecipe(RecipePost $recipe, string $apiKey): ?array
    {
        // 프롬프트: 제목 + 재료 + 각 조리 단계를 JSON 으로 번역
        $stepsForPrompt = collect($recipe->steps ?? [])
            ->map(fn($s, $i) => ['order' => $s['order'] ?? ($i + 1), 'text' => $s['text'] ?? ''])
            ->filter(fn($s) => trim($s['text']) !== '')
            ->values()
            ->all();

        $payload = [
            'title' => $recipe->title ?? '',
            'ingredients' => $recipe->ingredients ?? '',
            'steps' => $stepsForPrompt,
        ];

        $userPrompt = "Translate this Korean recipe to English. Return ONLY valid JSON matching this schema:\n"
            . "{\n"
            . '  "title_en": "English title",' . "\n"
            . '  "ingredients_en": "Plain English ingredient list joined by commas",' . "\n"
            . '  "ingredients_structured": [{"name_ko": "묵은 김치", "name_en": "Aged kimchi", "amount": "2컵"}],' . "\n"
            . '  "steps": [{"order": 1, "text_en": "English step description"}]' . "\n"
            . "}\n\n"
            . "Rules:\n"
            . "- Parse ingredients into structured array: extract Korean name, English translation, and amount (with units like g, ml, 컵, 큰술, 작은술, 개, 모, 대).\n"
            . "- If ingredients have sections like '주재료 > ...', use just the items (ignore section headers).\n"
            . "- name_ko: keep as-is in Korean (including numbers like ①②).\n"
            . "- amount: keep original Korean units.\n"
            . "- steps: return translations in SAME ORDER as input; order field must match input.\n"
            . "- title_en: natural English name.\n"
            . "- Use common American English ingredient names (e.g. Gochugaru, Gochujang, Doenjang can stay as-is with parenthesis).\n\n"
            . "Korean recipe:\n" . json_encode($payload, JSON_UNESCAPED_UNICODE);

        $res = Http::timeout(60)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'You translate Korean recipes to English. Output only valid JSON.'],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
            ]);

        if (!$res->ok()) {
            throw new \Exception('OpenAI ' . $res->status() . ': ' . substr($res->body(), 0, 200));
        }

        $content = $res->json('choices.0.message.content');
        if (!$content) return null;

        $parsed = json_decode($content, true);
        if (!is_array($parsed)) return null;

        // 번역 결과를 기존 steps 에 병합 (image_url 유지)
        $mergedSteps = null;
        if (!empty($parsed['steps']) && is_array($parsed['steps'])) {
            $trans = collect($parsed['steps'])->keyBy('order');
            $mergedSteps = collect($recipe->steps ?? [])->map(function ($s) use ($trans) {
                $order = $s['order'] ?? 0;
                $s['text_en'] = $trans[$order]['text_en'] ?? null;
                return $s;
            })->all();
        }

        return [
            'title_en' => $parsed['title_en'] ?? null,
            'ingredients_en' => $parsed['ingredients_en'] ?? null,
            'ingredients_structured' => $parsed['ingredients_structured'] ?? null,
            'steps' => $mergedSteps,
        ];
    }
}
