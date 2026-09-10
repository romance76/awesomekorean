<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * 임시 진단용 마이그레이션: 직전 마이그레이션(2026_05_04_100000)이
 * 97건 중 0건도 채우지 못한 원인을 서버 로그 접근 없이 확인하기 위해,
 * Openverse 검색 → 이미지 다운로드 → Intervention 리사이즈 → 저장까지
 * 각 단계 결과를 이미 존재하는 테스트용 더미 아이템(id=323, "asdfas")의
 * content 필드에 JSON으로 기록한다. /api/market/323 으로 바로 확인 가능.
 * 원인 파악 후 이 마이그레이션 파일은 삭제 예정 — 실제 콘텐츠 데이터를
 * 남기기 위한 것이 아님.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('market_items')) {
            return;
        }

        $log = ['step' => 'start'];

        try {
            $log['step'] = 'search';
            $resp = Http::withHeaders(['User-Agent' => 'AwesomeKoreanBot/1.0 (debug)'])
                ->timeout(6)->get('https://api.openverse.org/v1/images/', [
                    'q' => 'bicycle',
                    'license_type' => 'commercial',
                    'source' => 'flickr',
                    'page_size' => 3,
                ]);
            $log['search_ok'] = $resp->ok();
            $log['search_status'] = $resp->status();
            $results = $resp->ok() ? ($resp->json('results') ?? []) : [];
            $log['result_count'] = count($results);
            $url = $results[0]['url'] ?? null;
            $log['first_url'] = $url;

            if ($url) {
                $log['step'] = 'download';
                $imgResp = Http::withHeaders(['User-Agent' => 'AwesomeKoreanBot/1.0 (debug)'])->timeout(8)->get($url);
                $log['download_ok'] = $imgResp->ok();
                $log['download_status'] = $imgResp->status();
                $log['download_bytes'] = strlen($imgResp->body());

                if ($imgResp->ok() && strlen($imgResp->body()) > 2000) {
                    $log['step'] = 'intervention_class_exists';
                    $log['intervention_class_exists'] = class_exists(\Intervention\Image\Laravel\Facades\Image::class);

                    $log['step'] = 'intervention_read';
                    $img = \Intervention\Image\Laravel\Facades\Image::read($imgResp->body());
                    $log['step'] = 'intervention_resize';
                    $img->scaleDown(400, 400);
                    $log['step'] = 'intervention_encode';
                    $bytes = $img->toJpeg(70)->toString();
                    $log['intervention_ok'] = true;
                    $log['output_bytes'] = strlen($bytes);

                    $log['step'] = 'storage_put';
                    Storage::disk('public')->put('market/_debug_test.jpg', $bytes);
                    $log['storage_exists_after_put'] = Storage::disk('public')->exists('market/_debug_test.jpg');
                }
            }

            $log['step'] = 'done';
        } catch (\Throwable $e) {
            $log['exception_class'] = get_class($e);
            $log['exception_message'] = $e->getMessage();
            $log['exception_file'] = $e->getFile() . ':' . $e->getLine();
        }

        \App\Models\MarketItem::where('id', 323)->update(['content' => json_encode($log, JSON_PRETTY_PRINT)]);
    }

    public function down(): void
    {
        // 진단용이라 되돌리지 않음.
    }
};
