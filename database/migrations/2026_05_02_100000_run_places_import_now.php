<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

/**
 * 업소록(businesses)이 현재 전부 DatabaseSeeder가 만든 더미 데이터라
 * google_place_id가 전부 NULL임(실사용 Google Places 데이터가 하나도
 * 없음) — 즉 이미 등록되어 있던 매일 새벽 3시반 자동 스케줄
 * (places:import, routes/console.php)이 실제로는 한 번도 성공적으로
 * 실행된 적이 없는 것으로 보임(API 키 미설정 또는 스케줄러 미동작 등).
 *
 * 배포 파이프라인(SSH 명령 타임아웃 20분)을 벗어나지 않도록 최대 신규
 * 150건으로 제한해서 지금 1회 실행 — 실제로 동작하는지 확인하고 우선
 * 눈에 보이는 실제 업소 데이터를 채워 넣는다. 이후 전체 물량은 기존
 * 야간 자동 스케줄이 이어서 채우거나, 필요하면
 * .github/workflows/import-places.yml 을 GitHub Actions 화면에서
 * 수동으로 실행해 제한 없이 돌릴 수 있음.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('businesses')) {
            return;
        }

        try {
            Artisan::call('places:import', ['--limit' => 150]);
            Log::info('[places:import via migration] ' . trim(Artisan::output()));
        } catch (\Throwable $e) {
            Log::warning('[places:import via migration] failed: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // 실제로 임포트된 진짜 업소 데이터를 삭제하지 않음.
    }
};
