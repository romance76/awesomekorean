<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * 진단 로그(#31)로 확인한 실제 원인: 전체 108건 중 대부분 항목이 1장에서
 * 멈추는 이유는 Flickr가 막혀서가 아니라, Openverse 검색 결과 자체가
 * Flickr 위주라서 그걸 걸러내고 나면 대체 후보 URL이 몇 개 안 남기
 * 때문이었음(연속 실패 안전장치는 소진되지 않음 — consecutive_failures_
 * at_end=1로 정상 종료).
 *
 * 검색 시 가져오는 후보 개수를 늘려(page_size, take 배수 상향) 필터링
 * 후에도 목표치(3장)를 채울 만큼 대체 URL이 남도록 수정 후 이어서 실행.
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
