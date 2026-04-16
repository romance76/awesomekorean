<?php

namespace App\Console\Commands;

use App\Models\Business;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class DownloadGoogleImages extends Command
{
    protected $signature = 'businesses:download-google-images {--limit=0 : 최대 처리 수} {--dry-run : 실제 다운로드 없이 카운트만}';
    protected $description = 'Google CDN 이미지를 로컬 서버에 다운로드하여 저장';

    private $success = 0;
    private $failed = 0;
    private $skipped = 0;

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $businesses = Business::whereNotNull('images')
            ->where('images', 'LIKE', '%googleapis%')
            ->get(['id', 'google_place_id', 'images']);

        $total = $businesses->count();
        $this->info("Google CDN 이미지가 있는 업체: {$total}개");

        if ($dryRun) {
            $imgCount = 0;
            foreach ($businesses as $b) {
                $imgs = is_array($b->images) ? $b->images : json_decode($b->images, true);
                foreach ($imgs as $img) {
                    if (str_contains($img, 'googleapis.com')) $imgCount++;
                }
            }
            $this->info("총 Google 이미지: {$imgCount}장 (dry-run, 다운로드 안 함)");
            return 0;
        }

        $processed = 0;
        $chunks = $businesses->chunk(50);

        foreach ($chunks as $chunk) {
            foreach ($chunk as $business) {
                if ($limit > 0 && $processed >= $limit) break 2;

                $this->processBusinessImages($business);
                $processed++;

                if ($processed % 50 === 0) {
                    $this->info("[{$processed}/{$total}] 성공: {$this->success} / 실패: {$this->failed} / 스킵: {$this->skipped}");
                }
            }
        }

        $this->newLine();
        $this->info("=== 완료 ===");
        $this->info("처리 업체: {$processed}");
        $this->info("성공: {$this->success}장");
        $this->info("실패: {$this->failed}장");
        $this->info("스킵: {$this->skipped}장");

        return 0;
    }

    private function processBusinessImages(Business $business)
    {
        $imgs = is_array($business->images) ? $business->images : json_decode($business->images, true);
        if (!is_array($imgs)) return;

        $updated = false;
        $newImages = [];
        $failCount = 0;

        foreach ($imgs as $idx => $imgUrl) {
            if (!str_contains($imgUrl, 'googleapis.com')) {
                $newImages[] = $imgUrl;
                continue;
            }

            // 이미 3회 이상 실패하면 이 업체 스킵
            if ($failCount >= 3) {
                $this->skipped++;
                $newImages[] = $imgUrl; // 원본 유지
                continue;
            }

            $localPath = $this->downloadAndSave($imgUrl, $business->google_place_id ?: $business->id, $idx);

            if ($localPath) {
                $newImages[] = $localPath;
                $this->success++;
                $updated = true;
            } else {
                $newImages[] = $imgUrl; // 실패 시 원본 유지
                $this->failed++;
                $failCount++;
            }

            usleep(500000); // 0.5초 딜레이
        }

        if ($updated) {
            $business->update(['images' => $newImages]);
        }
    }

    private function downloadAndSave(string $url, $identifier, int $index): ?string
    {
        try {
            $response = Http::timeout(15)->get($url);

            if (!$response->successful()) {
                $this->warn("  HTTP {$response->status()} for ID:{$identifier}_{$index}");
                return null;
            }

            $content = $response->body();
            if (strlen($content) < 1000) {
                $this->warn("  Too small ({$identifier}_{$index}): " . strlen($content) . " bytes");
                return null;
            }

            // 리사이즈 (600x600 max, 비율 유지)
            $image = Image::read($content);
            $image->scaleDown(600, 600);
            $resized = $image->toJpeg(80)->toString();

            // 저장
            $filename = md5($identifier . '_' . $index . '_' . time()) . '.jpg';
            $path = 'businesses/' . $filename;
            Storage::disk('public')->put($path, $resized);

            return $path;
        } catch (\Exception $e) {
            $this->warn("  Error ({$identifier}_{$index}): " . substr($e->getMessage(), 0, 80));
            return null;
        }
    }
}
