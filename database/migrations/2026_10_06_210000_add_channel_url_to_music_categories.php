<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 관리자가 직접 만든 음악 카테고리(예: "트로트새바람")에 YouTube 채널
 * URL/핸들을 지정하면, 자동수집(auto_fetch)이 그 채널의 업로드 영상만
 * 가져오도록 함. 지금까지는 자동수집이 항상 "한국 음악 + 카테고리 이름"
 * 같은 일반 키워드로 YouTube 전체를 검색해서, 특정 채널 콘텐츠만
 * 원했던 카테고리에도 관련 없는 영상이 계속 섞여 들어가던 문제가 있었음.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('music_categories', function (Blueprint $table) {
            // korean_queries/pop_queries 뒤에 이어 붙이고 싶지만, 그 두 컬럼이
            // 이 저장소의 어떤 마이그레이션에도 정의돼 있지 않음(운영 DB에
            // 수동으로 추가된 것으로 보임) — after()가 존재 보장이 안 되는
            // 컬럼에 의존하면 신규/스테이징 환경에서 깨지므로 위치 지정 없이 추가.
            $table->string('channel_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('music_categories', function (Blueprint $table) {
            if (Schema::hasColumn('music_categories', 'channel_url')) {
                $table->dropColumn('channel_url');
            }
        });
    }
};
