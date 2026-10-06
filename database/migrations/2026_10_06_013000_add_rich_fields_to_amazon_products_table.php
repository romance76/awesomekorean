<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('amazon_products', function (Blueprint $table) {
            // 관리자가 직접 입력하는 참고용 가격 — Amazon 실시간 가격이 아님(자동 동기화 안 함)
            $table->decimal('price', 10, 2)->nullable()->after('image_url');
            // Amazon 상품 이미지 URL 여러 장 (Amazon 호스팅 URL만 저장, 서버에 재호스팅하지 않음)
            $table->json('amazon_image_urls')->nullable()->after('price');
            // 관리자가 직접 촬영/업로드한 이미지 — Amazon 소유 이미지가 아니므로 서버에 저장 가능
            $table->json('own_image_urls')->nullable()->after('amazon_image_urls');
        });
    }

    public function down(): void
    {
        Schema::table('amazon_products', function (Blueprint $table) {
            $table->dropColumn(['price', 'amazon_image_urls', 'own_image_urls']);
        });
    }
};
