<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * 뉴스/헤드라인/주식/쇼츠/음악/레시피/업소록/부동산/중고장터/정보 11개 자동 수집을
 * 순서대로 실행. 관리자 대시보드 "전체 콘텐츠 자동 수집" 버튼이 이 커맨드를
 * 백그라운드로 실행시킴 — 한 HTTP 요청 안에서 동기 처리하면 nginx 타임아웃에
 * 걸려 "수집 실패"로 끊기는 문제가 있었음(실측 확인).
 *
 * 각 단계가 시작/완료/실패될 때마다 storage/logs/manual-sync-all-progress.json에
 * 구조화된 진행 상태를 같이 남김 — 관리자 화면이 이 파일을 폴링해 "지금 뭐가
 * 돌고 있고 뭐가 끝났는지"를 실시간 체크리스트로 보여줄 수 있도록.
 *
 * 부동산/중고장터는 이 커맨드에 빠져 있어서(routes/console.php의 스케줄에만
 * 있었음) "전체 콘텐츠 자동 수집" 버튼을 눌러도 안 가져와지는 것처럼 보이던
 * 문제가 있었음 — 추가. 정보는 Artisan 커맨드가 아니라 별도 세션(Claude
 * 루틴)이 처리하는 구조라, 여기서는 생성 요청만 등록(info:trigger-generation)
 * 해두면 시간당 체크인 루틴이 그 요청을 보고 실제 생성을 수행한다.
 */
class SyncAllContent extends Command
{
    protected $signature   = 'content:sync-all';
    protected $description = '뉴스/헤드라인/주식/쇼츠/음악/레시피/업소록/부동산/중고장터/정보 전체 수집 (관리자 수동 실행용)';

    /** @var array<int, array{key:string, label:string, status:string, message:?string}> */
    private array $progress = [];

    public function handle(): int
    {
        $this->progress = array_map(
            fn($s) => $s + ['status' => 'pending', 'message' => null],
            [
                ['key' => 'news',             'label' => '뉴스'],
                ['key' => 'headlines',        'label' => '언론사 헤드라인'],
                ['key' => 'stocks',           'label' => '주식 시세'],
                ['key' => 'shorts',           'label' => '쇼츠'],
                ['key' => 'music',            'label' => '음악'],
                ['key' => 'recipes',          'label' => '레시피'],
                ['key' => 'places',           'label' => '업소록'],
                ['key' => 'realestate_sale',  'label' => '부동산(매매)'],
                ['key' => 'realestate_rent',  'label' => '부동산(렌트)'],
                ['key' => 'market_scrape',    'label' => '중고장터'],
                ['key' => 'info',             'label' => '정보'],
            ]
        );
        $this->writeProgress(false);

        $run = function (string $key, string $label, callable $fn) {
            $this->info("=== {$label} ===");
            $this->markStep($key, 'running');
            try {
                $fn();
                $this->info("[{$label}] 완료");
                $this->markStep($key, 'done');
            } catch (\Throwable $e) {
                $this->warn("[{$label}] 실패: " . $e->getMessage());
                $this->markStep($key, 'failed', $e->getMessage());
            }
        };

        $run('news', '뉴스', fn() => \Artisan::call('news:fetch'));
        $run('headlines', '언론사 헤드라인', fn() => \Artisan::call('headlines:fetch'));
        $run('stocks', '주식 시세', fn() => \Artisan::call('market:fetch'));
        $run('shorts', '쇼츠', function () {
            // 한국 비율은 shorts:fetch 안에서 관리자 설정(board.shorts.korea_ratio)을
            // 직접 읽으므로 여기서 하드코딩하지 않음.
            \Artisan::call('shorts:fetch', ['--limit' => 100]);
            $output = \Artisan::output();
            if (str_contains($output, '할당량') || str_contains($output, '403')) {
                throw new \RuntimeException('YouTube API 할당량 초과');
            }
        });
        $run('music', '음악', function () {
            \Artisan::call('music:fetch', ['--daily' => 100]);
            $output = \Artisan::output();
            if (str_contains($output, '할당량') || str_contains($output, '403')) {
                throw new \RuntimeException('YouTube API 할당량 초과');
            }
        });
        $run('recipes', '레시피', fn() => \Artisan::call('recipes:sync-all', ['--end' => 300]));
        $run('places', '업소록', fn() => \Artisan::call('places:import', ['--limit' => 50]));
        $run('realestate_sale', '부동산(매매)', fn() => \Artisan::call('realestate:scrape', ['--type' => 'sale']));
        $run('realestate_rent', '부동산(렌트)', fn() => \Artisan::call('realestate:scrape', ['--type' => 'rent']));
        $run('market_scrape', '중고장터', fn() => \Artisan::call('market:scrape'));
        $run('info', '정보', fn() => \Artisan::call('info:trigger-generation'));

        $this->info('=== 전체 완료 ===');
        $this->writeProgress(true);
        return self::SUCCESS;
    }

    private function markStep(string $key, string $status, ?string $message = null): void
    {
        foreach ($this->progress as &$step) {
            if ($step['key'] === $key) {
                $step['status'] = $status;
                $step['message'] = $message;
                // 화면에서 "지금 몇 초째 돌고 있는지" 보여줄 수 있도록 — 뉴스처럼
                // 여러 외부 API를 순서대로 호출하는 단계는 몇 분씩 걸릴 수 있는데,
                // 전체 진행률 바만 보면 멈춘 것처럼 보이던 문제를 보완.
                if ($status === 'running') $step['started_at'] = now()->toIso8601String();
                if (in_array($status, ['done', 'failed'], true)) $step['finished_at'] = now()->toIso8601String();
            }
        }
        unset($step);
        $this->writeProgress(false);
    }

    private function writeProgress(bool $done): void
    {
        file_put_contents(
            storage_path('logs/manual-sync-all-progress.json'),
            json_encode(['updated_at' => now()->toIso8601String(), 'done' => $done, 'steps' => $this->progress]),
            LOCK_EX
        );
    }
}
