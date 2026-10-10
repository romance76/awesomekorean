<?php

namespace App\Console\Commands;

use App\Support\SweepstakesAutomation;
use Illuminate\Console\Command;

class RunSweepstakesSchedules extends Command
{
    protected $signature = 'sweepstakes:run-schedules {--dry-run : 만들지 않고 몇 건이 대상인지만 센다}';
    protected $description = '반복 일정에서 경품 추첨 이벤트를 자동 생성하고, 자동 추첨 일정의 종료된 이벤트는 당첨자를 선정한다';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $created = SweepstakesAutomation::runDueSchedules($dry);
        $drawn = $dry ? 0 : SweepstakesAutomation::autoDraw();
        if ($created || $drawn) {
            $this->info(($dry ? '[dry-run] ' : '') . "이벤트 생성 {$created}건 · 자동 추첨 {$drawn}건");
        }
        return self::SUCCESS;
    }
}
