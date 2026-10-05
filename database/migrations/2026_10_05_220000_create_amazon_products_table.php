<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('amazon_products', function (Blueprint $table) {
            $table->id();
            $table->string('asin', 20)->unique();
            $table->string('amazon_url');
            $table->string('affiliate_url');
            $table->string('title');
            $table->string('image_url')->nullable();
            $table->string('category', 50)->nullable();
            $table->text('our_description')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();
        });

        // 클릭 로그 — 상품 삭제 시 로그까지 같이 지우면 "어떤 카테고리가 인기 있는지"
        // 같은 과거 집계가 날아가므로, product_id는 유지하되 FK 제약 없이 asin/카테고리를
        // 같이 저장해 상품이 삭제돼도 집계는 보존되도록 함.
        Schema::create('amazon_product_clicks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('amazon_product_id');
            $table->string('asin', 20);
            $table->string('category', 50)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('page')->nullable();
            $table->timestamp('clicked_at')->useCurrent();
            $table->index(['amazon_product_id', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_product_clicks');
        Schema::dropIfExists('amazon_products');
    }
};
