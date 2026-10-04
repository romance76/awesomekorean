<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// '정보' 탭 자동 생성 파이프라인 전용 토큰 — 외부에서 발급받는 키가 아니라 이번
// 작업에서 직접 생성한 값이므로, 사용자가 별도로 발급/입력할 필요 없이 바로
// api_keys 테이블에 시드해둔다 (IngestAuth 미들웨어의 fallback 경로).
return new class extends Migration {
    public function up(): void
    {
        DB::table('api_keys')->updateOrInsert(
            ['service' => 'info_ingest_token'],
            [
                'name' => '정보 탭 자동생성 Ingest 토큰',
                'api_key' => '8240fded1d44aa60596a5926ca847206b1502600',
                'description' => '자동 콘텐츠 생성 파이프라인이 /api/info-ingest/* 호출 시 사용하는 Bearer 토큰',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('api_keys')->where('service', 'info_ingest_token')->delete();
    }
};
