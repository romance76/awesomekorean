<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

// 히어로 배너: 핸드폰용 "영어" 이미지 칸. 이제 이미지는 PC 한글·PC 영어·핸드폰 한글·핸드폰 영어 네 가지이고,
// 영어 이미지가 없으면 같은 기기의 한글 이미지로 대신 보여 준다.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hero_banners') || Schema::hasColumn('hero_banners', 'image_url_mobile_en')) return;
        Schema::table('hero_banners', function (Blueprint $t) {
            $t->string('image_url_mobile_en', 500)->nullable()->after('image_url_mobile');
        });
        Cache::forget('hero_banners_public_v2');
    }

    public function down(): void
    {
        // 데이터 보호: 칸을 지우지 않는다.
    }
};
