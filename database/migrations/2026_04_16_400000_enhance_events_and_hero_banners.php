<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // events 테이블에 공식 이벤트 관련 컬럼 추가
        Schema::table('events', function (Blueprint $table) {
            $table->string('event_type', 20)->default('user')->after('category');
            $table->boolean('is_pinned')->default(false)->after('event_type');
            $table->string('banner_image', 500)->nullable()->after('image_url');
            $table->string('banner_subtitle', 200)->nullable()->after('banner_image');
            $table->string('banner_color', 20)->default('#F5A623')->after('banner_subtitle');
            $table->string('event_url', 500)->nullable()->after('url');
            $table->integer('reward_points')->default(0)->after('price');
        });

        // 히어로 배너 테이블 (메인 홈 상단 슬라이드)
        Schema::create('hero_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->string('subtitle', 200)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('bg_color', 20)->default('#F5A623');
            $table->string('text_color', 20)->default('#FFFFFF');
            $table->string('link_type', 20)->default('url'); // url, event, page, none
            $table->string('link_url', 500)->nullable();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('link_page', 100)->nullable(); // /music, /chat 등
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['event_type', 'is_pinned', 'banner_image', 'banner_subtitle', 'banner_color', 'event_url', 'reward_points']);
        });
        Schema::dropIfExists('hero_banners');
    }
};
