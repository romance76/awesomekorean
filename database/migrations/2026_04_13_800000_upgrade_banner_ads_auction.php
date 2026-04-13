<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banner_ads', function (Blueprint $table) {
            // 옥션 입찰 금액 (월간)
            if (!Schema::hasColumn('banner_ads', 'bid_amount')) {
                $table->unsignedInteger('bid_amount')->default(0)->after('total_cost');
            }
            // 타겟 페이지들 (JSON: ["community","qa","jobs"] 또는 ["home"])
            if (!Schema::hasColumn('banner_ads', 'target_pages')) {
                $table->json('target_pages')->nullable()->after('page');
            }
            // 슬롯 번호 (left-1, left-2, right-1 등)
            if (!Schema::hasColumn('banner_ads', 'slot_number')) {
                $table->unsignedTinyInteger('slot_number')->default(1)->after('position');
            }
            // 경매 월 (2026-05 등)
            if (!Schema::hasColumn('banner_ads', 'auction_month')) {
                $table->string('auction_month', 7)->nullable()->after('end_date');
            }
            // 경매 순위 (1=최고입찰)
            if (!Schema::hasColumn('banner_ads', 'auction_rank')) {
                $table->unsignedTinyInteger('auction_rank')->default(0)->after('auction_month');
            }
        });
    }

    public function down(): void
    {
        Schema::table('banner_ads', function (Blueprint $table) {
            $table->dropColumn(['bid_amount', 'target_pages', 'slot_number', 'auction_month', 'auction_rank']);
        });
    }
};
