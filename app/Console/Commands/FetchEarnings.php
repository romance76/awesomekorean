<?php

namespace App\Console\Commands;

use App\Services\EarningsCalendar;
use Illuminate\Console\Command;

class FetchEarnings extends Command
{
    protected $signature   = 'earnings:fetch {--from=-1 : 시작 주(0=이번 주)} {--to=2 : 끝 주}';
    protected $description = '미국 실적 발표(어닝) 일정 수집 (지난주~2주 뒤)';

    public function handle(EarningsCalendar $cal): int
    {
        for ($w = (int) $this->option('from'); $w <= (int) $this->option('to'); $w++) {
            $monday = EarningsCalendar::mondayOf($w);
            $n = $cal->syncWeek($monday);
            $this->info("{$monday->toDateString()} 주: {$n}건");
        }
        \App\Models\EarningsEvent::where('report_date', '<', now('America/New_York')->subDays(21)->toDateString())->delete();
        return self::SUCCESS;
    }
}
