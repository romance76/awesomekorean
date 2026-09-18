<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 언론사 헤드라인을 별도 테이블 대신 news 테이블(is_external=true)에
// 통합 저장하도록 바뀌면서 더 이상 쓰이지 않는 테이블을 정리.
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('external_headlines');
    }

    public function down(): void
    {
        Schema::create('external_headlines', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('source_slug');
            $table->string('title');
            $table->string('summary', 300)->nullable();
            $table->string('source_url')->unique();
            $table->string('image_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index('published_at');
        });
    }
};
