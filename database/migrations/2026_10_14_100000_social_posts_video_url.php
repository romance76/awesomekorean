<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SNS 게시에 영상(릴스·스레드 영상) 주소 칸 추가. 값이 있으면 사진 대신 영상으로 올린다.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('social_posts') && !Schema::hasColumn('social_posts', 'video_url')) {
            Schema::table('social_posts', function (Blueprint $t) {
                $t->string('video_url', 500)->nullable()->after('image_url');   // 공개 https 주소(mp4, 9:16)
            });
        }
    }

    public function down(): void
    {
        // 데이터 보호: 되돌리지 않는다.
    }
};
