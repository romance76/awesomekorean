<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * page_size 회귀 수정(#33) 배포 후 실제 진전 확인: 108건 중 이미지
 * 있는 항목 55→59, 3장 완료 항목 17→26으로 증가. 다만 진단 로그에
 * search_api_failed=true가 남아있고 연속 다운로드 실패 6회로 74건만
 * 처리한 채 조기 중단됨 — 오늘 Openverse API를 매우 많이 호출해서
 * 익명 요청 일일/시간당 한도에 걸리기 시작한 것으로 추정됨.
 *
 * 한도가 일시적일 수 있으므로 이어서 재실행 — 안 되면(계속
 * search_api_failed=true + 조기 진전 없음) 다음 단계로 유료/무료 API
 * 키 발급이나 시간을 두고 재시도하는 방안을 검토해야 함.
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
