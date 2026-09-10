<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * 이전 마이그레이션(230000, --force 없이 재실행)이 실제로 2분 12초간
 * 돌았는데도(=진짜로 뭔가 시도는 함) DB상 채워진 건수(50/100)가 전혀
 * 늘지 않았음 — 어떤 이유로든 이번 배치는 100% 실패했다는 뜻인데,
 * SSH/로그 접근이 없어서 정확한 원인을 알 수 없었음.
 *
 * 커맨드에 실행 요약(대상 건수/채움/스킵/조기중단 사유/소요시간)을
 * market_items id=323.content에 남기도록 진단 로깅을 추가했으므로,
 * 이번 실행 후 그 값을 읽어 정확한 원인을 확인할 수 있음.
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
