<?php

namespace App\Console\Commands;

use App\Models\JobPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * 기존 job_posts 중 zipcode 는 있지만 lat/lng 가 없는 레코드를
 * zippopotam.us 로 지오코딩해서 채워넣음.
 *
 * 실행: php artisan jobs:backfill-geocode
 */
class BackfillJobGeocode extends Command
{
    protected $signature = 'jobs:backfill-geocode {--limit=500} {--dry : 실제 업데이트 없이 결과만 출력}';
    protected $description = 'Backfill lat/lng for job_posts that have zipcode but no coords';

    public function handle(): int
    {
        $dry = $this->option('dry');
        $limit = (int) $this->option('limit');

        $posts = JobPost::whereNotNull('zipcode')
            ->where('zipcode', '!=', '')
            ->where(function ($q) {
                $q->whereNull('lat')->orWhereNull('lng')
                  ->orWhere('lat', 0)->orWhere('lng', 0);
            })
            ->limit($limit)
            ->get();

        $this->info("Found {$posts->count()} job posts missing lat/lng");
        $updated = 0; $failed = 0;

        foreach ($posts as $p) {
            $zip = trim($p->zipcode);
            if (!preg_match('/^\d{5}$/', $zip)) { $failed++; continue; }

            $geo = Cache::remember("geo_zip_{$zip}", now()->addHours(24), function () use ($zip) {
                $ctx = stream_context_create(['http' => ['timeout' => 3]]);
                $resp = @file_get_contents("https://api.zippopotam.us/us/{$zip}", false, $ctx);
                if (!$resp) return null;
                $d = json_decode($resp, true);
                $place = $d['places'][0] ?? null;
                if (!$place) return null;
                return [
                    'lat' => (float) $place['latitude'],
                    'lng' => (float) $place['longitude'],
                    'city' => $place['place name'] ?? null,
                    'state' => $place['state abbreviation'] ?? null,
                ];
            });

            if (!$geo) {
                $this->warn("  #{$p->id} zip={$zip} → 지오코딩 실패");
                $failed++;
                continue;
            }

            $this->line("  #{$p->id} [{$p->title}] zip={$zip} → {$geo['lat']},{$geo['lng']} ({$geo['city']}, {$geo['state']})");

            if (!$dry) {
                $p->lat = $geo['lat'];
                $p->lng = $geo['lng'];
                if (!$p->city) $p->city = $geo['city'];
                if (!$p->state) $p->state = $geo['state'];
                $p->save();
            }
            $updated++;

            // rate limit: zippopotam 무료 API 배려
            usleep(100000); // 100ms
        }

        $this->info("Done. Updated: {$updated}, Failed: {$failed}" . ($dry ? ' (dry run)' : ''));
        return self::SUCCESS;
    }
}
