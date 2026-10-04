<?php

namespace App\Console\Commands;

use App\Models\MarketItem;
use Illuminate\Console\Command;

// 30일 지난 자동 수집 중고 매물(source=scraped)만 삭제한다. 회원이 직접 올린
// 매물(source=user)은 scraped_at이 항상 NULL이라 이 조건에 절대 걸리지 않는다.
class ExpireScrapedMarket extends Command
{
    protected $signature = 'market:expire-scraped {--days=30}';
    protected $description = '30일 지난 자동 수집 중고 매물 삭제 (회원이 올린 매물은 건드리지 않음)';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $deleted = MarketItem::scraped()
            ->where('scraped_at', '<', now()->subDays($days))
            ->delete();

        $this->info("{$days}일 지난 자동 수집 중고 매물 {$deleted}건 삭제");
        return self::SUCCESS;
    }
}
