<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Throwable;

class SweepstakesTick extends Command
{
    protected $signature = 'sweepstakes:tick';
    protected $description = '경품 추첨 매분 작업(추첨 5분 전 알림 · 당첨 안내 · 반복 일정 자동 실행)을 한 번에 실행';

    private const STEPS = [
        'sweepstakes:send-reminders',
        'sweepstakes:notify-winners',
        'sweepstakes:run-schedules',
    ];

    public function handle(): int
    {
        $failed = 0;
        foreach (self::STEPS as $name) {
            try {
                $this->call($name);
            } catch (Throwable $e) {
                // 한 단계가 실패해도 나머지는 계속 실행
                $failed++;
                report($e);
            }
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
