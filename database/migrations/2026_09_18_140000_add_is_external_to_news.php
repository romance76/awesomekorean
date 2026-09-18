<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 오마이뉴스(전체 본문 재배포 가능)와 그 외 언론사(제목+썸네일+짧은 요약만
// 가능) 기사를 같은 news 테이블/피드/상세페이지에서 함께 보여주기 위한 구분
// 컬럼. is_external=true 인 기사는 content가 비어 있고 summary만 채워지며,
// 상세페이지에서는 요약 + 원문 링크로 표시된다.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->boolean('is_external')->default(false)->after('source_url');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn('is_external');
        });
    }
};
