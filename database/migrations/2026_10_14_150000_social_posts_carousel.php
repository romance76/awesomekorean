<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SNS 게시: 인스타 여러 장(캐러셀) 사진 목록 + 스레드 이어 쓰기(답글 사슬) 칸 추가 (JSON 글자로 저장)
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('social_posts')) return;
        Schema::table('social_posts', function (Blueprint $t) {
            if (!Schema::hasColumn('social_posts', 'images')) $t->text('images')->nullable()->after('video_url');
            if (!Schema::hasColumn('social_posts', 'thread_parts')) $t->text('thread_parts')->nullable()->after('images');
        });
    }

    public function down(): void
    {
        // 데이터 보호: 되돌리지 않는다.
    }
};
