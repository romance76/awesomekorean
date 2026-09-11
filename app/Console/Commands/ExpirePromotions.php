<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Club;
use App\Models\JobPost;
use App\Models\MarketItem;
use App\Models\RealEstateListing;
use Illuminate\Console\Command;

class ExpirePromotions extends Command
{
    protected $signature = 'promotions:expire';
    protected $description = '만료된 상위노출(프로모션) 정기 해제 (매분 실행) — 이전엔 목록 페이지가 열릴 때만 지연 해제되어 트래픽 낮은 곳은 계속 노출될 수 있었음';

    public function handle(): void
    {
        $models = [JobPost::class, RealEstateListing::class, MarketItem::class, Club::class, Business::class];

        $total = 0;
        foreach ($models as $model) {
            $total += $model::where('promotion_tier', '!=', 'none')
                ->where('promotion_expires_at', '<', now())
                ->update(['promotion_tier' => 'none', 'promotion_expires_at' => null]);
        }

        if ($total > 0) {
            $this->info("만료 프로모션 {$total}건 해제 완료.");
        }
    }
}
