<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * 메모리 초과 문제를 고쳤는데도 여전히 0건 채움 — 원인은 연속 실패
 * 안전장치가 전체 실행 기준(아이템 단위 리셋 없음)이라, 어느 한 아이템의
 * 검색 결과가 우연히 Flickr(계속 403) 위주로 나오면 그 자리에서 바로
 * 소진되어 이후 정상 작동하는 Wikimedia 결과가 많은 다른 아이템까지
 * 전부 못 가보고 중단됐던 것으로 추정. Flickr URL을 아예 후보에서
 * 제외하도록 코드 수정 후 재실행.
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
