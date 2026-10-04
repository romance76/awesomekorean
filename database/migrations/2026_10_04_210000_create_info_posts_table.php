<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "정보" 탭 — info.awesomekorean.com에 있던 미국 한인 생활정보 가이드 콘텐츠를
// 어썸코리안 본 사이트로 통합하기 위한 테이블. 검색엔진 노출이 목적이라
// title/slug/meta_title/meta_description을 명확히 분리해서 저장한다.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('info_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('body'); // 제한된 HTML(h2/p/ul/li/a/img)
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('keyword_term')->nullable();
            $table->string('cover_image_url')->nullable();
            // 생활정보/이민·비자/세금/금융/보험/부동산/교통/교육/날씨·안전/통신/창업·비즈니스
            $table->string('category');
            $table->unsignedInteger('view_count')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['is_published', 'published_at']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('info_posts');
    }
};
