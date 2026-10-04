<?php

namespace App\Console\Commands;

use App\Models\MarketItem;
use Illuminate\Console\Command;

// 중고장터 더미/테스트 매물을 정리한다 (소프트 운영 전환, 1회성 청소 명령).
// source=scraped(eBay에서 긁어온 진짜 매물)만 남기고 그 외 전부(source=user,
// market:seed 더미 포함)를 삭제한다 — 사이트가 아직 정식 오픈 전이라 보호할
// 실제 회원 데이터가 없는 경우 전용. 기본은 미리보기만 하고, --force를 줘야
// 실제로 삭제한다.
class CleanupDummyMarket extends Command
{
    protected $signature = 'market:cleanup-dummy {--force : 실제로 삭제 실행 (기본은 미리보기만)}';
    protected $description = 'source=scraped(eBay 실매물)만 남기고 중고장터 더미/테스트 매물 전부 삭제';

    public function handle(): int
    {
        $query = MarketItem::where(function ($q) {
            $q->where('source', '!=', 'scraped')->orWhereNull('source');
        });

        $matched = $query->get(['id', 'title', 'category', 'price']);

        if ($matched->isEmpty()) {
            $this->info('조건에 맞는 더미 매물이 없습니다 (이미 정리되었거나 원래 없음).');
            return self::SUCCESS;
        }

        $this->info("더미/테스트 매물 {$matched->count()}건 발견:");
        foreach ($matched->take(50) as $row) {
            $this->line("  #{$row->id} {$row->title} ({$row->category}) - \${$row->price}");
        }
        if ($matched->count() > 50) {
            $this->line('  ... 외 ' . ($matched->count() - 50) . '건');
        }

        if (!$this->option('force')) {
            $this->warn('미리보기만 했습니다. 실제로 삭제하려면 --force 옵션을 붙여 다시 실행하세요.');
            return self::SUCCESS;
        }

        $ids = $matched->pluck('id');
        $deleted = MarketItem::whereIn('id', $ids)->delete();
        $this->info("삭제 완료: {$deleted}건");

        return self::SUCCESS;
    }
}
