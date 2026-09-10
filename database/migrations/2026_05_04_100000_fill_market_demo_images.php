<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * 중고장터 더미 아이템(108건) 중 대부분이 이미지가 없어서, 오픈 라이선스
 * 이미지 검색(Openverse)으로 각 아이템 제목에 맞는 이미지를 최소 3장씩 채움.
 * 배포 SSH 타임아웃(20분) 안에서 안전하게 끝나도록 --limit=200(현재 전체
 * 커버 가능)으로 제한. market:fill-demo-images 커맨드 자체는 이미지가
 * 이미 3장 이상인 아이템은 건너뛰므로 재실행해도 안전(idempotent).
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
        // 채워 넣은 이미지를 삭제하지 않음 (다른 데이터 마이그레이션과 동일한 정책).
    }
};
