<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 홈 화면 "언론사별" 헤드라인 위젯용 — 오마이뉴스(News 모델)와 달리 본문을
// 재호스팅하지 않고 제목/썸네일/링크만 저장, 클릭하면 원문 사이트로 이동.
// 대부분의 언론사 RSS는 전체 본문 재배포 권한이 없어(라이선스 문제) 이 방식이 맞음.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('external_headlines', function (Blueprint $table) {
            $table->id();
            $table->string('source');        // 언론사명 (예: 아시아경제)
            $table->string('source_slug');   // 로고/색상 매핑용 (예: asiae)
            $table->string('title');
            $table->string('source_url')->unique();
            $table->string('image_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_headlines');
    }
};
