<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * 뉴스 시스템을 SBS + TIME RSS 전용으로 재구성.
 * - 영어 원문 컬럼 추가 (TIME 번역용)
 * - 기존 뉴스 전부 삭제
 * - 카테고리를 8개 통합 카테고리로 교체
 */
return new class extends Migration {
    public function up(): void
    {
        // 1. 영어 원문 컬럼 추가
        Schema::table('news', function (Blueprint $table) {
            if (!Schema::hasColumn('news', 'title_en')) {
                $table->string('title_en')->nullable()->after('title');
            }
            if (!Schema::hasColumn('news', 'content_en')) {
                $table->text('content_en')->nullable()->after('content');
            }
        });

        // 2. 기존 뉴스 전부 삭제 (RSS 전용으로 재시작)
        DB::statement('DELETE FROM news');

        // 3. 카테고리 재구성 — 8개 통합 (하위 카테고리 없음)
        DB::statement('UPDATE news SET category_id = NULL');
        DB::statement('DELETE FROM news_categories');
        DB::statement('ALTER TABLE news_categories AUTO_INCREMENT = 1');

        $categories = [
            ['name' => '정치',   'slug' => 'politics'],
            ['name' => '경제',   'slug' => 'economy'],
            ['name' => '사회',   'slug' => 'society'],
            ['name' => '생활문화', 'slug' => 'lifestyle'],
            ['name' => '국제',   'slug' => 'world'],
            ['name' => '스포츠',  'slug' => 'sports'],
            ['name' => 'IT과학', 'slug' => 'tech'],
            ['name' => '연예',   'slug' => 'entertainment'],
        ];

        foreach ($categories as $cat) {
            DB::table('news_categories')->insert([
                'name' => $cat['name'],
                'slug' => $cat['slug'],
                'parent_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn(['title_en', 'content_en']);
        });
    }
};
