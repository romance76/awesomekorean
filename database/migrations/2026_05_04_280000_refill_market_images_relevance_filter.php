<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * 사용자 리포트: 실제 채워진 사진들 중 상당수가 제목과 전혀 무관함
 * (예: "자전거 Specialized Allez Sport" 매물에 축구장 사진, "유니클로
 * 히트텍" 매물에 달력 사진). Openverse 인덱스에 태그가 부정확한 사진이
 * 섞여 있고, 특히 폴백으로 쓰는 느슨한 카테고리 검색어("clothing",
 * "furniture" 등)에서 무관한 결과가 잘 걸러지지 않았던 것이 원인.
 *
 * 검색 결과의 제목/태그에 검색어(또는 폴백 검색어) 관련 단어가 실제로
 * 포함되는지 최소한의 관련성 검사를 추가(예시 쿼리로 실측: "clothing"
 * 검색 결과 18건 중 7건만 관련, "thermal underwear"는 18건 중 11건).
 * 이미 잘못 채워진 이미지도 있으므로 --force로 전체 재실행해서 교체.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('market_items')) {
            return;
        }

        try {
            Artisan::call('market:fill-demo-images', ['--limit' => 200, '--per-item' => 3, '--force' => true]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[market:fill-demo-images --force via migration] failed: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // 이미지를 삭제하지 않음 (다른 데이터 마이그레이션과 동일한 정책).
    }
};
