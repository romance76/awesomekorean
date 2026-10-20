<?php

namespace App\Console\Commands;

use App\Support\EventFeedImporter;
use Illuminate\Console\Command;

/**
 * 공식 지역 행사 피드(관광청·시청·카운티)에서 중요한 행사만 골라 가져온다. 매주 일요일 자동 실행(routes/console.php).
 *  php artisan events:import-feeds                 켜 둔 모든 출처
 *  php artisan events:import-feeds --source=ga_explore_gwinnett   한 출처만(꺼져 있어도)
 */
class ImportEventFeeds extends Command
{
    protected $signature = 'events:import-feeds {--source= : 출처 키(예: ga_explore_gwinnett). 없으면 켜 둔 모든 출처}';
    protected $description = '공식 지역 행사 피드에서 중요한 행사 가져오기(주 10 · 카운티 3 · 도시 2)';

    public function handle(): int
    {
        set_time_limit(0);
        $r = EventFeedImporter::run($this->option('source') ?: null);
        $failed = 0;
        foreach ($r['sources'] as $s) {
            $this->line(sprintf('%s: 후보 %d · 선택 %d%s', $s['key'], $s['candidates'], $s['selected'] ?? 0, $s['error'] ? ' ⚠ ' . $s['error'] : ''));
            if ($s['error']) $failed++;
        }
        $this->info("합계: 선택 {$r['selected']} · 새로 {$r['created']} · 갱신 {$r['updated']} · 정리 {$r['removed']}");
        return $failed && !$r['selected'] ? self::FAILURE : self::SUCCESS;
    }
}
