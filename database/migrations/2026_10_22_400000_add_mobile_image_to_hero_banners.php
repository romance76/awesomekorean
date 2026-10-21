<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

// 히어로 배너(홈 상단 슬라이드)에 "핸드폰용 이미지"를 따로 올릴 수 있게 한다.
// 있으면 폰에서는 이 그림을 그대로(비율 유지) 보여 주고, 없으면 예전처럼 제목·소제목 글자 카드로 보여 준다.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hero_banners') || Schema::hasColumn('hero_banners', 'image_url_mobile')) return;
        Schema::table('hero_banners', function (Blueprint $t) {
            $t->string('image_url_mobile', 500)->nullable()->after('image_url_en');
        });
        Cache::forget('hero_banners_public_v2');
    }

    public function down(): void
    {
        // 데이터 보호: 칸을 지우지 않는다.
    }
};
