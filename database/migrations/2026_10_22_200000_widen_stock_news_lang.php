<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 증권가 뉴스 lang 칸이 2글자(ko·en)만 들어가게 만들어져 있어, 번역 전 영문 기사를 뜻하는 'en_raw'(6글자)를 저장할 때
// "Data too long for column 'lang'" 으로 수집 명령이 통째로 실패했다 — 10글자까지 늘린다.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_news', function (Blueprint $t) {
            $t->string('lang', 10)->default('ko')->change();
        });
    }

    public function down(): void
    {
        // 되돌리지 않음 (좁히면 'en_raw' 기사가 잘려 오류가 난다)
    }
};
