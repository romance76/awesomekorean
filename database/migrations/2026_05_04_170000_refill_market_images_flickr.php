<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * 진단 결과: Openverse 검색과 Flickr 이미지 다운로드 모두 정상 작동함이
 * 확인됨(직전 배포에서 겪은 403은 첫 시도 때 108개 아이템 x 3장을 텀 없이
 * 연속 요청해서 생긴 일시적 요청 제한이었던 것으로 보임). 이번엔 이미지
 * 한 장씩 받을 때마다 0.3초 텀 + 403/429 발생 시 1회 재시도 로직을 추가한
 * 버전으로 실제 채우기를 실행. --force로 아까 진단 과정에서 오염된
 * id=323("asdfas") 아이템의 content도 이 실행에서 이미지가 채워지며
 * 자연스럽게 함께 처리됨(진단용 JSON은 그대로 content에 남지만 어차피
 * 테스트용 쓰레기 게시글이라 문제 없음).
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

        // 진단 과정에서 테스트용 더미 아이템(id=323)의 content 필드에 남은 디버그 JSON 정리
        \App\Models\MarketItem::where('id', 323)->update(['content' => '테스트 게시글입니다.']);
    }

    public function down(): void
    {
        // 이미지를 삭제하지 않음 (다른 데이터 마이그레이션과 동일한 정책).
    }
};
