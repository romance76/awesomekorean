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
            foreach ($widths as $w) {
                $total++;
                $path = $this->cachePath($url, $w);
                if (!$force && file_exists($path)) { $skip++; continue; }
                $body = $this->fetchUrl($url);
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
            $q = DB::table('businesses')->whereNotNull('images')->where('images', '!=', '[]')->where('images', '!=', '');
            $bar = $this->output->createProgressBar($q->count());
            $q->orderBy('id')->chunk(50, function ($rows) use ($process, $bar) {
                foreach ($rows as $r) {
                    $imgs = is_string($r->images) ? json_decode($r->images, true) : $r->images;
                    if (is_array($imgs) && isset($imgs[0])) $process($imgs[0]);
                    $bar->advance();
                }
            });
            $bar->finish();
            $this->newLine();
        }

        if ($model === 'all' || $model === 'news') {
            $this->info('→ News');
            $q = DB::table('news_articles')->whereNotNull('image_url')->where('image_url', '!=', '');
            $bar = $this->output->createProgressBar($q->count());
            $q->orderBy('id')->chunk(50, function ($rows) use ($process, $bar) {
                foreach ($rows as $r) { $process($r->image_url); $bar->advance(); }
            });
            $bar->finish();
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
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SomeKorean-Thumb/1.0)',
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
