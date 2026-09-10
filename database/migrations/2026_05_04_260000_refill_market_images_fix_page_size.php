<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * 직전 수정(#32, page_size 확대)이 실제로는 전면 회귀였음 — 진단
 * 로그로 확인: filled=0, skipped=107, 18.6초 만에 종료. Openverse API를
 * 직접 테스트해본 결과 익명 요청은 page_size가 20을 넘으면 401로
 * 거부됨을 확인(min(40, count*12)=36으로 설정했던 게 원인). 20 이하로
 * 맞추고(min(20, count*6)), 검색 API가 비정상 응답을 줄 때 조용히
 * 넘어가지 않고 로그에 남기도록 추가한 뒤 재실행.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('market_items')) {
            return;
        }

        try {
            Artisan::call('market:fill-demo-images', ['--limit' => 200, '--per-item' => 3]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[market:fill-demo-images via migration] failed: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // 이미지를 삭제하지 않음 (다른 데이터 마이그레이션과 동일한 정책).
    }
};
