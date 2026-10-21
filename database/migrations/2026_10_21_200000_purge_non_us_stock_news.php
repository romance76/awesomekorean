<?php

use App\Console\Commands\FetchStockNews;
use App\Models\StockNews;
use Illuminate\Database\Migrations\Migration;

// 증권가 뉴스는 미국 주식 관련만 — 처음에 들어온 한국 증시 기사를 새 기준으로 걸러 지운다
return new class extends Migration {
    public function up(): void
    {
        $ids = [];
        StockNews::where('lang', 'ko')->orderBy('id')->chunk(500, function ($rows) use (&$ids) {
            foreach ($rows as $r) {
                if (!FetchStockNews::isUsStock($r->title, (string) $r->summary)) $ids[] = $r->id;
            }
        });
        foreach (array_chunk($ids, 500) as $chunk) StockNews::whereIn('id', $chunk)->delete();
    }

    public function down(): void
    {
        // 지운 기사는 되돌리지 않음 (수집이 다시 채움)
    }
};
