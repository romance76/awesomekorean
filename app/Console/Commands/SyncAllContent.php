<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * 뉴스/헤드라인/주식/쇼츠/음악/레시피/업소록 7개 자동 수집을 순서대로 실행.
 * 관리자 대시보드 "전체 콘텐츠 자동 수집" 버튼이 이 커맨드를 백그라운드로
 * 실행시킴 — 7개를 한 HTTP 요청 안에서 동기 처리하면 nginx 타임아웃에
 * 걸려 "수집 실패"로 끊기는 문제가 있었음(실측 확인).
 */
class SyncAllContent extends Command
{
    protected $signature   = 'content:sync-all';
    protected $description = '뉴스/헤드라인/주식/쇼츠/음악/레시피/업소록 전체 수집 (관리자 수동 실행용)';

    public function handle(): int
    {
        $run = function (string $label, callable $fn) {
            $this->info("=== {$label} ===");
            try {
                $fn();
                $this->info("[{$label}] 완료");
            } catch (\Throwable $e) {
                $this->warn("[{$label}] 실패: " . $e->getMessage());
            }
        };

        $run('뉴스', fn() => \Artisan::call('news:fetch'));
        $run('언론사 헤드라인', fn() => \Artisan::call('headlines:fetch'));
        $run('주식 시세', fn() => \Artisan::call('market:fetch'));
        $run('쇼츠', function () {
            \Artisan::call('shorts:fetch', ['--limit' => 100, '--korean-ratio' => 75]);
            $output = \Artisan::output();
            if (str_contains($output, '할당량') || str_contains($output, '403')) {
                throw new \RuntimeException('YouTube API 할당량 초과');
            }
        });
        $run('음악', function () {
            \Artisan::call('music:fetch', ['--daily' => 100]);
            $output = \Artisan::output();
            if (str_contains($output, '할당량') || str_contains($output, '403')) {
                throw new \RuntimeException('YouTube API 할당량 초과');
            }
        });
        $run('레시피', fn() => \Artisan::call('recipes:sync-all', ['--end' => 300]));
        $run('업소록', fn() => \Artisan::call('places:import', ['--limit' => 50]));

        $this->info('=== 전체 완료 ===');
        return self::SUCCESS;
    }
}
