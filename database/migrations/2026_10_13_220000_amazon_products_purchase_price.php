<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 내돈내산 리뷰: 작성자가 실제로 낸 가격과 구매 날짜 (현재 판매가가 아니라 "구매 당시" 정보로만 보여 준다)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amazon_products', function (Blueprint $table) {
            if (!Schema::hasColumn('amazon_products', 'purchase_price')) $table->decimal('purchase_price', 10, 2)->nullable()->after('price');
            if (!Schema::hasColumn('amazon_products', 'purchased_on')) $table->date('purchased_on')->nullable()->after('purchase_price');
        });
    }

    public function down(): void
    {
        Schema::table('amazon_products', function (Blueprint $table) {
            $table->dropColumn(['purchase_price', 'purchased_on']);
        });
    }
};
