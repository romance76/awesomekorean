<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 머니 카페 글에 그림·그래프 — 운영팀 글만 쓴다 (주제 이미지 경로 + 꾸밈 블록). 기존 글·데이터는 그대로.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('money_posts', function (Blueprint $t) {
            if (!Schema::hasColumn('money_posts', 'image')) $t->string('image', 120)->nullable()->after('content');
            if (!Schema::hasColumn('money_posts', 'blocks')) $t->json('blocks')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        // 데이터 보호: 칸을 지우지 않는다.
    }
};
