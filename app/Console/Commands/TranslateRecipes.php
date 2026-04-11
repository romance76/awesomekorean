<?php

namespace App\Console\Commands;

use App\Models\RecipePost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TranslateRecipes extends Command
{
    protected $signature = 'recipes:translate
        {--limit=50 : 번역 대상 레시피 수}
        {--all : 전체 번역}
        {--force : 이미 번역된 것도 재번역}
        {--engine=google : google / openai}';

    protected $description = '레시피 ingredients/steps 한영 번역 + 구조화';

    public function handle()
    {
        $engine = $this->option('engine');

        $query = RecipePost::query()->where('is_active', true);
        if (!$this->option('force')) {
            $query->whereNull('translated_at');
        }
        $limit = (int) $this->option('limit');
        if (!$this->option('all')) {
            $query->limit($limit);
        }

        $total = $query->count();
        $this->info("엔진: {$engine} · 번역 대상: {$total}개");
        if (!$total) return 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $success = 0;
        $failed = 0;

        $query->chunkById(20, function ($recipes) use (&$success, &$failed, $bar, $engine) {
            foreach ($recipes as $recipe) {
                try {
                    $result = $engine === 'openai'
                        ? $this->translateViaOpenAI($recipe)
                        : $this->translateViaGoogle($recipe);

                    if ($result) {
                        $updates = [
                            'translated_at' => now(),
                        ];
                        if (!empty($result['title_en'])) $updates['title_en'] = $result['title_en'];
                        if (!empty($result['ingredients_en'])) $updates['ingredients_en'] = $result['ingredients_en'];
                        if (!empty($result['ingredients_structured'])) $updates['ingredients_structured'] = $result['ingredients_structured'];
                        if (!empty($result['steps'])) $updates['steps'] = $result['steps'];
                        $recipe->update($updates);
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
                usleep(50000); // 0.05s rate-limit
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ 성공: {$success} / ❌ 실패: {$failed}");
        return 0;
    }

    /**
     * Google Translate 비공식 엔드포인트 (무료, 키 불필요)
     */
    private function googleTranslate(string $text, string $target = 'en', string $source = 'ko'): ?string
    {
        $text = trim($text);
        if ($text === '') return '';
        try {
            $res = Http::timeout(15)->get('https://translate.googleapis.com/translate_a/single', [
                'client' => 'gtx',
                'sl' => $source,
                'tl' => $target,
                'dt' => 't',
                'q' => $text,
            ]);
            if (!$res->ok()) return null;
            $arr = $res->json();
            if (!is_array($arr) || empty($arr[0])) return null;
            $out = '';
            foreach ($arr[0] as $seg) {
                if (isset($seg[0])) $out .= $seg[0];
            }
            return trim($out);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function translateViaGoogle(RecipePost $recipe): ?array
    {
        $out = [];

        // 1. 제목
        if ($recipe->title && !$recipe->title_en) {
            $out['title_en'] = $this->googleTranslate($recipe->title);
        }

        // 2. 재료 — 통째로 번역 후 구조화 파싱
        if ($recipe->ingredients) {
            $ingEn = $this->googleTranslate($recipe->ingredients);
            if ($ingEn) $out['ingredients_en'] = $ingEn;

            // 구조화: 한국어 원본을 파싱 + 각 재료 이름만 영어 번역
            $structured = $this->parseAndTranslateIngredients($recipe->ingredients);
            if ($structured) $out['ingredients_structured'] = $structured;
        }

        // 3. 조리 순서 — 각 step 번역
        if (is_array($recipe->steps) && count($recipe->steps)) {
            $newSteps = [];
            foreach ($recipe->steps as $s) {
                $text = $s['text'] ?? '';
                $textEn = $text ? $this->googleTranslate($text) : null;
                $newSteps[] = [
                    'order' => $s['order'] ?? (count($newSteps) + 1),
                    'text' => $text,
                    'text_en' => $textEn,
                    'image_url' => $s['image_url'] ?? null,
                ];
                usleep(30000); // 0.03s
            }
            $out['steps'] = $newSteps;
        }

        return $out ?: null;
    }

    /**
     * 식품안전나라 RCP_PARTS_DTLS 를 파싱해 [{name_ko, name_en, amount}] 배열로.
     *
     * 원본 포맷 다양함:
     *   "새우두부계란찜\n연두부 75g(3/4모), 칵테일새우 20g(5마리), ...\n고명\n시금치 10g(3줄기)"
     *   "주재료 > 아몬드가루 90g, 무가당 코코아가루① 12g, ..."
     *   "[ 2인분 ] 토마토(2개), 양파(¼개), 감자(¼개), 고추기름(1Ts)"
     *   "검은콩(200g), 메밀면(120g), 오이(30g), 방울토마토(1개)"
     */
    private function parseAndTranslateIngredients(string $text): ?array
    {
        // "[ X인분 ]" 프리픽스 제거
        $text = preg_replace('/\[\s*[0-9]+\s*인분\s*\]\s*/u', '', $text);
        // 숫자/분수/단위 감지용
        $hasQuantity = '/[0-9½⅓¼⅔¾⅛⅜⅝⅞]|약간|적당량/u';

        // "XXX >" 섹션 구분 → 줄바꿈으로
        $text = preg_replace('/([가-힣\w ]+)\s*>\s*/u', "\n", $text);
        $lines = preg_split('/[\n\r]+/', $text);

        $items = [];
        foreach ($lines as $line) {
            // 괄호 안 쉼표가 보호되도록 괄호 영역은 치환 후 복원
            $line = trim($line);
            if ($line === '') continue;

            // 괄호 안의 쉼표를 임시 토큰으로 치환
            $protected = preg_replace_callback('/\([^)]*\)/u', function ($m) {
                return str_replace(',', '<<COMMA>>', $m[0]);
            }, $line);

            $chunks = preg_split('/,\s*/', $protected);

            foreach ($chunks as $chunk) {
                // 보호 복원
                $chunk = str_replace('<<COMMA>>', ',', trim($chunk));
                if ($chunk === '') continue;

                // 숫자/분량 기호가 전혀 없는 줄은 섹션 헤더로 간주
                if (!preg_match($hasQuantity, $chunk)) {
                    continue;
                }

                $name = $chunk;
                $amount = '';

                // 1) 괄호 형식: "토마토(2개)" 또는 "검은콩(200g)" 또는 "고추기름(1Ts)"
                if (preg_match('/^(.+?)\s*[\(（]\s*(.+?)\s*[\)）]\s*$/u', $chunk, $m)) {
                    $name = trim($m[1]);
                    $amount = trim($m[2]);
                }
                // 2) "연두부 75g(3/4모)" — 공백 + 분량 + 부연
                elseif (preg_match('/^(.+?)\s+([0-9½⅓¼⅔¾⅛⅜⅝⅞.\/~]+\s*[가-힣a-zA-Z]*(?:\s*\([^)]*\))?)\s*$/u', $chunk, $m)) {
                    $name = trim($m[1]);
                    $amount = trim($m[2]);
                }
                // 3) "참기름 약간"
                elseif (preg_match('/^(.+?)\s+(약간|적당량)$/u', $chunk, $m)) {
                    $name = trim($m[1]);
                    $amount = trim($m[2]);
                }
                // 4) 마지막 fallback: 숫자 분리
                elseif (preg_match('/^(.+?)([0-9½⅓¼⅔¾]+.*)$/u', $chunk, $m)) {
                    $name = trim($m[1]);
                    $amount = trim($m[2]);
                }

                if ($name === '') continue;
                // 이름이 숫자/단위뿐이거나 빈 괄호 조각이면 건너뜀
                if (preg_match('/^[0-9.\/\s]+$/', $name)) continue;

                $items[] = [
                    'name_ko' => $name,
                    'name_en' => '',
                    'amount' => $amount,
                ];
            }
        }

        if (empty($items)) return null;

        // 이름들만 batch 번역
        $joined = implode(' ||| ', array_map(fn($i) => $i['name_ko'], $items));
        $translated = $this->googleTranslate($joined);
        if ($translated) {
            $parts = preg_split('/\s*\|{2,}\s*/', $translated);
            if (count($parts) < count($items)) {
                $parts = [];
                foreach ($items as $it) {
                    $parts[] = $this->googleTranslate($it['name_ko']) ?: '';
                    usleep(50000);
                }
            }
            foreach ($items as $i => &$item) {
                if (isset($parts[$i])) $item['name_en'] = trim($parts[$i]);
            }
            unset($item);
        }

        return $items;
    }

    /**
     * (선택) OpenAI 엔진
     */
    private function translateViaOpenAI(RecipePost $recipe): ?array
    {
        $apiKey = config('services.openai.key') ?: env('OPENAI_API_KEY');
        if (!$apiKey) {
            throw new \Exception('OPENAI_API_KEY 미설정');
        }

        $stepsForPrompt = collect($recipe->steps ?? [])
            ->map(fn($s, $i) => ['order' => $s['order'] ?? ($i + 1), 'text' => $s['text'] ?? ''])
            ->filter(fn($s) => trim($s['text']) !== '')
            ->values()->all();

        $payload = [
            'title' => $recipe->title ?? '',
            'ingredients' => $recipe->ingredients ?? '',
            'steps' => $stepsForPrompt,
        ];

        $userPrompt = "Translate this Korean recipe to English. Return ONLY valid JSON matching this schema:\n"
            . '{ "title_en": "...", "ingredients_en": "...", '
            . '"ingredients_structured": [{"name_ko": "...", "name_en": "...", "amount": "..."}], '
            . '"steps": [{"order": 1, "text_en": "..."}] }' . "\n\n"
            . "Korean recipe:\n" . json_encode($payload, JSON_UNESCAPED_UNICODE);

        $res = Http::timeout(60)
            ->withHeaders(['Authorization' => 'Bearer ' . $apiKey])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Output only valid JSON.'],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
            ]);

        if (!$res->ok()) throw new \Exception('OpenAI ' . $res->status());
        $parsed = json_decode($res->json('choices.0.message.content') ?? '', true);
        if (!is_array($parsed)) return null;

        // steps 병합 (image_url 유지)
        if (!empty($parsed['steps']) && is_array($parsed['steps'])) {
            $trans = collect($parsed['steps'])->keyBy('order');
            $parsed['steps'] = collect($recipe->steps ?? [])->map(function ($s) use ($trans) {
                $s['text_en'] = $trans[$s['order'] ?? 0]['text_en'] ?? null;
                return $s;
            })->all();
        }

        return $parsed;
    }
}
