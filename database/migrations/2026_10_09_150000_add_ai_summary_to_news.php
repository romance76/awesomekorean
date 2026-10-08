<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 뉴스 "AI 해설": 짧은 RSS 요약 아래에 Claude 가 원문을 읽고 자기 말로 정리한 해설을 붙인다.
 * ai_status: null=아직 안 함, done=해설 있음, skipped=원문을 못 읽었거나(유료/차단) 해설할 가치가 낮아 건너뜀
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->text('ai_summary')->nullable()->after('summary');
            $table->string('ai_status', 10)->nullable()->after('ai_summary');
            $table->timestamp('ai_summarized_at')->nullable()->after('ai_status');
            $table->index(['ai_status', 'published_at'], 'news_ai_status_published_idx');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropIndex('news_ai_status_published_idx');
            $table->dropColumn(['ai_summary', 'ai_status', 'ai_summarized_at']);
        });
    }
};
