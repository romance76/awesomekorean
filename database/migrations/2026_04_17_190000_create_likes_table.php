<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P2B-1: polymorphic likes 테이블 생성.
     * PostController::show 등에서 `likes` 테이블 조회 → 테이블 부재로 500 발생하던 문제 해결.
     */
    public function up(): void
    {
        if (Schema::hasTable('likes')) return;

        Schema::create('likes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('likeable_type', 255);
            $t->unsignedBigInteger('likeable_id');
            $t->timestamps();

            $t->unique(['user_id', 'likeable_type', 'likeable_id'], 'likes_unique');
            $t->index(['likeable_type', 'likeable_id'], 'likes_target');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('likes');
    }
};
