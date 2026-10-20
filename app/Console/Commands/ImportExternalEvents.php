<?php

namespace App\Console\Commands;

use App\Support\EventImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 외부 지역 행사(Ticketmaster)를 켜 둔 주마다 가져온다. 매일 새벽 자동 실행(routes/console.php).
 *  php artisan events:import            켜 둔 모든 주
 *  php artisan events:import --state=GA 한 주만(꺼져 있어도 실행)
 */
class ImportExternalEvents extends Command
{
    protected $signature = 'events:import {--state= : 주 코드(예: GA). 없으면 켜 둔 모든 주} {--days=90 : 앞으로 며칠 치}';
    protected $description = '외부 지역 행사(Ticketmaster) 가져오기';

    public function handle(): int
    {
        if (!EventImporter::apiKey()) {
            $this->error('Ticketmaster API 키가 없어요. 관리자 › 지역 행사 가져오기 에서 저장하세요.');
            return self::FAILURE;
        }
        $days = max(1, min(180, (int) $this->option('days')));
        $q = DB::table('event_import_states')->orderBy('sort_order');
        $one = strtoupper((string) $this->option('state'));
        $codes = $one ? $q->where('code', $one)->pluck('code') : $q->where('enabled', true)->pluck('code');
        if ($codes->isEmpty()) { $this->warn('가져올 주가 없어요.'); return self::SUCCESS; }

        $tot = ['fetched' => 0, 'created' => 0, 'updated' => 0]; $failed = 0;
        foreach ($codes as $code) {
            $r = EventImporter::importState($code, $days);
            $this->line(sprintf('%s: 가져옴 %d · 새로 %d · 갱신 %d%s', $code, $r['fetched'], $r['created'], $r['updated'], $r['error'] ? ' ⚠ ' . $r['error'] : ''));
            foreach ($tot as $k => $_) $tot[$k] += $r[$k];
            if ($r['error']) {
                $failed++;
                if (str_contains($r['error'], '401') || str_contains($r['error'], '429')) break; // 키 문제·한도 초과면 더 돌려도 소용없음
            }
        }
        $pruned = EventImporter::prune();
        $this->info("합계: 가져옴 {$tot['fetched']} · 새로 {$tot['created']} · 갱신 {$tot['updated']} · 정리 {$pruned}");
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
