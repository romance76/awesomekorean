<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * 진단 결과: Wikimedia 다운로드는 100% 성공(200)하는데도 실제로는 0건
 * 채워짐 — Openverse 검색 결과 중 6MB짜리 초고화질 원본까지 섞여 있어서,
 * Intervention Image로 디코딩할 때 PHP 메모리 한도(Allowed memory size
 * exhausted)를 넘겨 예외로 잡히지 않고 프로세스 자체가 죽었던 것으로
 * 추정됨. 다운로드 전 원본 용량 4MB 초과 시 건너뛰도록 필터 추가 +
 * CLI 프로세스 메모리 한도를 512M로 상향.
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
