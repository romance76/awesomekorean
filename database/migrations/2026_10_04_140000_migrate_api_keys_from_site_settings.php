<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// AdminSettingsController가 예전엔 api_keys 테이블이 아니라 site_settings의
// 'api_keys' JSON 블롭에 저장하고 있던 버그(이번 커밋에서 수정)가 있어서,
// 그동안 관리자 페이지에서 등록했던 키들이 전부 그 블롭 안에 갇혀 있었다.
// 실제 api_keys 테이블로 1회성 이관 — service 코드가 겹치면(예: youtube)
// 관리자가 마지막으로 등록한 값으로 덮어쓴다.
return new class extends Migration {
    public function up(): void
    {
        $setting = DB::table('site_settings')->where('key', 'api_keys')->first();
        if (!$setting || !$setting->value) return;

        $keys = json_decode($setting->value, true);
        if (!is_array($keys)) return;

        foreach ($keys as $k) {
            if (empty($k['service']) || empty($k['api_key'])) continue;

            DB::table('api_keys')->updateOrInsert(
                ['service' => $k['service']],
                [
                    'name' => $k['name'] ?? $k['service'],
                    'api_key' => $k['api_key'],
                    'description' => $k['description'] ?? null,
                    'is_active' => $k['is_active'] ?? true,
                    'created_at' => $k['created_at'] ?? now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        // 데이터 이관용 1회성 마이그레이션 — 되돌릴 필요 없음
    }
};
