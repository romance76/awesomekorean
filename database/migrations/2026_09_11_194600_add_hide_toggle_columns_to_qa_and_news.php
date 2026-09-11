<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 관리자의 Q&A/뉴스 "숨김" 토글이 실제로는 존재하지 않는 컬럼을 대상으로 해
// 완전히 장식용이던 문제 수정 (실측 확인: 토글해도 목록/상세 어디에도 반영 안 됨).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qa_posts', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('best_answer_id');
        });

        Schema::table('news', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('qa_posts', function (Blueprint $table) {
            $table->dropColumn('is_hidden');
        });

        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
