<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Flickr 제외 후에도 migrate 단계가 19초 만에 끝나(정상적인 100건 처리라면
 * 훨씬 오래 걸려야 함) 여전히 0건 채움 — 다운로드 자체가 아니라 이미지
 * 디코딩 단계에서 매번 즉시 실패하고 있는 것으로 의심됨.
 *
 * downloadAndStore()가 Intervention\Image\Laravel\Facades\Image 파사드를
 * 썼는데, 이 코드베이스에서 실제로 검증된 방식(CompressesUploads 트레이트,
 * 사용자 업로드 압축 기능에 실사용 중)은 new ImageManager(new GdDriver())를
 * 직접 생성하는 방식임 — 파사드는 서비스 프로바이더 등록 여부에 따라
 * 환경별로 바인딩 예외가 날 수 있어 검증된 방식으로 교체.
 * 실패 시 원인이 로그에 남도록 Log::warning도 추가함.
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
