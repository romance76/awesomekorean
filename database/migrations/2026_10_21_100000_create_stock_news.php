<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 증권 페이지 "증권가 뉴스" — 언론사 RSS의 제목·짧은 요약·원문 링크만 담는다(본문 재게시 안 함)
return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_news', function (Blueprint $t) {
            $t->id();
            $t->string('source', 40);
            $t->string('title', 500);
            $t->string('summary', 300)->nullable();
            $t->string('url', 1000);
            $t->char('url_hash', 40)->unique();
            $t->string('image_url', 1000)->nullable();
            $t->string('lang', 2)->default('ko');           // ko | en(제목을 한국어로 번역해 저장한 영문 기사)
            $t->timestamp('published_at')->nullable()->index();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_news');
    }
};
