<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 새로 제작된 배너 이미지들이 한글/영어 각각 텍스트가 이미지에 이미 그려져
// 있는 형태라, 언어별로 다른 이미지를 보여주고(image_url_en) 기존처럼
// 제목/부제 텍스트 오버레이를 켤지 끌지(image_only) 선택할 수 있게 함.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('hero_banners', function (Blueprint $table) {
            if (!Schema::hasColumn('hero_banners', 'image_url_en')) {
                $table->string('image_url_en')->nullable()->after('image_url');
            }
            if (!Schema::hasColumn('hero_banners', 'image_only')) {
                $table->boolean('image_only')->default(false)->after('image_url_en');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hero_banners', function (Blueprint $table) {
            if (Schema::hasColumn('hero_banners', 'image_only')) {
                $table->dropColumn('image_only');
            }
            if (Schema::hasColumn('hero_banners', 'image_url_en')) {
                $table->dropColumn('image_url_en');
            }
        });
    }
};
