<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 메인 화면 "인기 주식" 위젯 + 증권 페이지용 시세 캐시.
// 방문자 브라우저가 매번 외부 API를 직접 호출하면 비공식 API 특성상
// 차단/느려짐 위험이 있어, 서버에서 주기적으로 가져와 캐싱해 제공.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('market_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->unique(); // 예: ^IXIC, 005930.KS
            $table->string('name');
            $table->string('category'); // index | watchlist
            $table->decimal('price', 14, 2)->nullable();
            $table->decimal('change_pct', 8, 3)->nullable();
            $table->decimal('change', 14, 2)->nullable();
            $table->bigInteger('volume')->nullable();
            $table->json('sparkline')->nullable(); // 최근 1개월 종가 배열 (미니 차트용)
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_quotes');
    }
};
