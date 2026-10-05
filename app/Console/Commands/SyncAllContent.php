<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use Illuminate\Console\Command;

/**
 * 뉴스/헤드라인/주식/쇼츠/음악/레시피/업소록/부동산/중고장터/정보 10개 자동 수집을
 * 순서대로 실행. 관리자 대시보드 "전체 콘텐츠 자동 수집" 버튼이 이 커맨드를
 * 백그라운드로 실행시킴 — 한 HTTP 요청 안에서 동기 처리하면 nginx 타임아웃에
 * 걸려 "수집 실패"로 끊기는 문제가 있었음(실측 확인).
 *
 * 부동산/중고장터는 이 커맨드에 빠져 있어서(routes/console.php의 스케줄에만
 * 있었음) "전체 콘텐츠 자동 수집" 버튼을 눌러도 안 가져와지는 것처럼 보이던
 * 문제가 있었음 — 추가. 정보는 Artisan 커맨드가 아니라 별도 세션(Claude
 * 루틴)이 처리하는 구조라, 여기서는 생성 요청 상태만 'running'으로 걸어두면
 * 시간당 체크인 루틴이 그 요청을 보고 실제 생성을 수행함.
 */
class SyncAllContent extends Command
{
    protected $signature   = 'content:sync-all';
    protected $description = '뉴스/헤드라인/주식/쇼츠/음악/레시피/업소록/부동산/중고장터/정보 전체 수집 (관리자 수동 실행용)';

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
            // 한국 비율은 shorts:fetch 안에서 관리자 설정(board.shorts.korea_ratio)을
            // 직접 읽으므로 여기서 하드코딩하지 않음.
            \Artisan::call('shorts:fetch', ['--limit' => 100]);
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
        $run('부동산(매매)', fn() => \Artisan::call('realestate:scrape', ['--type' => 'sale']));
        $run('부동산(렌트)', fn() => \Artisan::call('realestate:scrape', ['--type' => 'rent']));
        $run('중고장터', fn() => \Artisan::call('market:scrape'));
        $run('정보', function () {
            $raw = SiteSetting::where('key', 'info_generation_status')->value('value');
            $status = $raw ? json_decode($raw, true) : null;
            if (($status['status'] ?? null) === 'running') {
                $this->info('이미 생성 진행 중 — 요청 건너뜀');
                return;
            }
            SiteSetting::updateOrCreate(['key' => 'info_generation_status'], [
                'value' => json_encode([
                    'status' => 'running', 'completed' => 0, 'target' => 10,
                    'requested_by' => '전체 콘텐츠 자동 수집', 'started_at' => now()->toIso8601String(),
                ]),
                'group' => 'info',
            ]);
            $this->info('생성 요청 등록됨 — 시간당 체크인 루틴이 실제 생성을 수행함(최대 1시간 내 시작)');
        });

        $this->info('=== 전체 완료 ===');
        return self::SUCCESS;
    }
}
