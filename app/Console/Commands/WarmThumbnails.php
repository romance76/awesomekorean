<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WarmThumbnails extends Command
{
    protected $signature = 'thumbs:warm {model=all : recipes|businesses|news|shopping|all} {--widths=240,480} {--force : regenerate even if exists} {--mod=} {--total=}';
    protected $description = 'Pre-generate thumbnails for list images so first page load is instant';

    private array $allowedHosts = [
        'foodsafetykorea.go.kr', 'www.foodsafetykorea.go.kr',
        'i.ytimg.com', 'img.youtube.com', 'yt3.ggpht.com',
        'lh3.googleusercontent.com', 'lh4.googleusercontent.com',
        'lh5.googleusercontent.com', 'lh6.googleusercontent.com',
        'maps.googleapis.com', 'maps.gstatic.com',
        // 한국 뉴스 이미지 호스트
        'www.chosun.com', 'chosun.com', 'img.chosun.com',
        'flexible.img.hani.co.kr', 'img.hani.co.kr', 'www.hani.co.kr',
        'dimg.donga.com', 'image.donga.com', 'img.donga.com',
        'img.sbs.co.kr', 'mimgnews.pstatic.net', 'imgnews.pstatic.net',
        'news.kbs.co.kr', 'img.kbs.co.kr',
        'newsimg.hankookilbo.com', 'www.hankookilbo.com',
        'www.koreadaily.com', 'koreadaily.com',
        // TIME magazine CDN
        'api.time.com', 'time.com', 'www.time.com',
        'i0.wp.com', 'i1.wp.com', 'i2.wp.com',
        'somekorean.com', 'www.somekorean.com',
    ];

    public function handle()
    {
        $model = $this->argument('model');
        $widths = array_map('intval', explode(',', $this->option('widths')));
        $force = (bool) $this->option('force');

        $this->info("Warming thumbnails (widths: " . implode(',', $widths) . ")");

        $total = 0; $ok = 0; $skip = 0; $fail = 0;

        $process = function ($url) use ($widths, $force, &$total, &$ok, &$skip, &$fail) {
            if (!$url) return;
            // HTML 엔티티 디코딩 (뉴스 URL 에 &amp; 섞여 있음)
            $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5);
            // Business images stored as "businesses/xxx.jpg" (no leading /)
            if (!str_starts_with($url, 'http') && !str_starts_with($url, '/')) {
                $url = '/storage/' . $url;
            }
            foreach ($widths as $w) {
                $total++;
                $path = $this->cachePath($url, $w);
                if (!$force && file_exists($path)) { $skip++; continue; }
                $body = $this->loadBody($url);
                if (!$body || strlen($body) < 100) { $fail++; continue; }
                try {
                    $this->writeResized($body, $path, $w);
                    $ok++;
                } catch (\Throwable $e) {
                    $fail++;
                }
            }
        };

        $mod = $this->option('mod');
        $totalWorkers = $this->option('total');
        $useShard = ($mod !== null && $totalWorkers !== null);

        if ($model === 'all' || $model === 'recipes') {
            $this->info('→ Recipes' . ($useShard ? " [worker $mod/$totalWorkers]" : ''));
            $q = DB::table('recipe_posts')->whereNotNull('thumbnail')->where('thumbnail', '!=', '');
            if ($useShard) $q->whereRaw('id % ? = ?', [(int)$totalWorkers, (int)$mod]);
            $q->orderBy('id')->chunk(50, function ($rows) use ($process) {
                foreach ($rows as $r) { $process($r->thumbnail); }
            });
            $this->newLine();
        }

        if ($model === 'all' || $model === 'businesses') {
            $this->info('→ Businesses');
            // 로컬 이미지(/storage/businesses/...) 만 warmup. Google Places photo URL 은
            // photoreference 가 만료되어 403 을 반환하므로 skip.
            $q = DB::table('businesses')
                ->whereNotNull('images')
                ->where('images', '!=', '[]')
                ->where('images', '!=', '')
                ->where('images', 'like', '%businesses/%');
            if ($useShard) $q->whereRaw('id % ? = ?', [(int)$totalWorkers, (int)$mod]);
            $q->orderBy('id')->chunk(50, function ($rows) use ($process) {
                foreach ($rows as $r) {
                    $imgs = is_string($r->images) ? json_decode($r->images, true) : $r->images;
                    if (!is_array($imgs)) continue;
                    // Google Places URL 은 건너뛰고 첫 번째 로컬 이미지만 처리
                    foreach ($imgs as $img) {
                        if (is_string($img) && !str_contains($img, 'maps.googleapis.com')) {
                            $process($img);
                            break;
                        }
                    }
                }
            });
            $this->newLine();
        }

        if ($model === 'all' || $model === 'news') {
            $this->info('→ News' . ($useShard ? " [worker $mod/$totalWorkers]" : ''));
            $q = DB::table('news')->whereNotNull('image_url')->where('image_url', '!=', '');
            if ($useShard) $q->whereRaw('id % ? = ?', [(int)$totalWorkers, (int)$mod]);
            $q->orderBy('id')->chunk(50, function ($rows) use ($process) {
                foreach ($rows as $r) { $process($r->image_url); }
            });
            $this->newLine();
        }

        if ($model === 'all' || $model === 'shopping') {
            if (DB::getSchemaBuilder()->hasTable('shopping_deals')) {
                $this->info('→ Shopping');
                $q = DB::table('shopping_deals')->whereNotNull('image_url')->where('image_url', '!=', '');
                $bar = $this->output->createProgressBar($q->count());
                $q->orderBy('id')->chunk(50, function ($rows) use ($process, $bar) {
                    foreach ($rows as $r) { $process($r->image_url); $bar->advance(); }
                });
                $bar->finish();
                $this->newLine();
            }
        }

        $this->info("DONE: total=$total ok=$ok skip=$skip fail=$fail");
        return 0;
    }

    private function cachePath(string $url, int $width): string
    {
        $hash = md5($url);
        $dir = storage_path("app/public/thumbs/" . substr($hash, 0, 2));
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        return $dir . '/' . substr($hash, 2) . "_{$width}.jpg";
    }

    // /storage/ 내부 파일 또는 외부 HTTP URL 에서 이미지 바이트 로드
    private function loadBody(string $url): ?string
    {
        if (str_starts_with($url, '/storage/')) {
            $abs = public_path($url);
            if (!file_exists($abs)) return null;
            return file_get_contents($abs) ?: null;
        }
        return $this->fetchUrl($url);
    }

    private function fetchUrl(string $url): ?string
    {
        $parsed = parse_url($url);
        $host = strtolower($parsed['host'] ?? '');
        if (!in_array($host, $this->allowedHosts)) return null;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code < 200 || $code >= 400) return null;
        return $body;
    }

    private function writeResized(string $body, string $cachePath, int $width): void
    {
        $src = @imagecreatefromstring($body);
        if ($src === false) throw new \Exception('invalid image');

        $sw = imagesx($src);
        $sh = imagesy($src);
        $ratio = $sh > 0 ? $sw / $sh : 1;
        $tw = $width;
        $th = max(1, (int) round($width / $ratio));

        $dst = imagecreatetruecolor($tw, $th);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $sw, $sh);
        imagejpeg($dst, $cachePath, 82);
        imagedestroy($src);
        imagedestroy($dst);
    }
}
