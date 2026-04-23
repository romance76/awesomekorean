<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('popup_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);                      // 관리용 제목
            $table->enum('type', ['image', 'text'])->default('text');
            $table->string('image_url', 500)->nullable();      // type=image 일 때
            $table->text('content')->nullable();               // type=text 일 때 (HTML 허용)
            $table->integer('width')->default(500);            // text 팝업 가로 px
            $table->integer('height')->default(300);           // text 팝업 세로 px
            $table->string('link_url', 500)->nullable();       // 클릭 시 이동 (선택)
            $table->enum('display_mode', ['once_per_day', 'every_visit'])->default('once_per_day');
            $table->boolean('is_active')->default(true);
            $table->timestamp('start_at')->nullable();         // 게시 시작
            $table->timestamp('end_at')->nullable();           // 게시 종료
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active','start_at','end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popup_banners');
    }
};
