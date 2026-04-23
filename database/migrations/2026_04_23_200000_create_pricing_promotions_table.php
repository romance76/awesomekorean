<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pricing_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);                      // 관리용 제목 (예: 신년 40% 할인)
            $table->integer('discount_pct');                   // 1~95
            $table->boolean('applies_to_ads')->default(false);
            $table->boolean('applies_to_packages')->default(false);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active','starts_at','ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_promotions');
    }
};
