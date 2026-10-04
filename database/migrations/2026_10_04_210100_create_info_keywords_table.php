<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// info.awesomekorean.com의 keywords 테이블과 동일한 역할 — 자동 생성 루틴이
// 소비할(혹은 참고할) 키워드 큐.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('info_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('term');
            $table->string('source')->nullable(); // ai_pipeline, manual 등
            $table->unsignedInteger('search_volume')->nullable();
            $table->string('competition')->nullable();
            $table->string('status')->default('new'); // new, used
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('info_keywords');
    }
};
