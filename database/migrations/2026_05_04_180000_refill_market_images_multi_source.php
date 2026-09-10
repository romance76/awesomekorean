<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * 이전 마이그레이션(2026_05_04_170000)은 Flickr 단일 소스 제한 상태에서
 * 안전장치(연속 실패 조기 중단)가 정상 작동해 "성공적으로 완료"됐지만
 * 실제로는 0건 채움 — migrations 테이블에 완료로 기록돼버려서, 그 다음
 * source 제한을 없앤 코드 수정(다중 소스 혼합)은 재실행되지 않았음.
 * 새 타임스탬프로 다시 호출해서 실제로 반영시킴.
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
