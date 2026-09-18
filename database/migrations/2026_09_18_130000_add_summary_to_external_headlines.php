<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 헤드라인 클릭 시 언론사 사이트로 바로 튕기지 않고, 우리 사이트 안에서
// 짧은 요약을 먼저 보여주기 위한 컬럼. RSS description에서 이미지 태그를
// 제거한 순수 텍스트 일부만 저장 (본문 전체 재배포 아님 — 저작권 안전).
return new class extends Migration {
    public function up(): void
    {
        Schema::table('external_headlines', function (Blueprint $table) {
            $table->string('summary', 300)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('external_headlines', function (Blueprint $table) {
            $table->dropColumn('summary');
        });
    }
};
