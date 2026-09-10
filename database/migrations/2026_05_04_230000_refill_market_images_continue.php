<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Intervention Image 파사드를 직접 ImageManager 생성 방식으로 바꾼 수정(#29)이
 * 실제로 원인이 맞았음 — 배포 후 100건 중 50건이 실제 제목에 맞는 이미지로
 * 채워짐을 확인. 다만 실행 시간 상한(420초) 안에 전체를 다 처리하지
 * 못해 나머지 50건은 여전히 이미지가 없고, 채워진 것 중에도 목표치
 * 3장을 못 채운 항목이 있음.
 *
 * --force 없이 재실행 — 커맨드의 기본 쿼리(이미지 없거나 개수가
 * per-item 미만인 것만 대상)가 이미 이 상황에 맞게 동작하므로, 이미
 * 3장을 다 채운 항목은 건드리지 않고 나머지만 이어서 처리함.
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
