<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

// 메인 메뉴에서 뺀 게임이 사이트 맨 아래 푸터에 "게임" 링크로 남아 있던 것을 지운다.
// 관리자가 저장해 둔 푸터 설정(footer_config)이 코드의 기본 푸터를 덮어쓰고 있어서, 기본값만 고쳐서는 안 지워졌다.
// 저장된 푸터에서 /games 링크만 빼고 나머지(다른 링크·문구·SNS)는 그대로 둔다. 링크가 없으면 아무것도 안 한다(여러 번 돌려도 안전).
return new class extends Migration
{
    /** 순수 변환: 푸터 설정에서 주소가 $urls(또는 그 하위)인 링크만 뺀다 */
    public function strip(array $cfg, array $urls): array
    {
        if (!isset($cfg['columns']) || !is_array($cfg['columns'])) return $cfg;
        foreach ($cfg['columns'] as $i => $col) {
            if (!is_array($col) || !isset($col['links']) || !is_array($col['links'])) continue;
            $kept = [];
            foreach ($col['links'] as $link) {
                $u = is_array($link) ? rtrim((string) ($link['url'] ?? ''), '/') : '';
                $drop = false;
                foreach ($urls as $x) if ($u === $x || str_starts_with($u, $x . '/')) $drop = true;
                if (!$drop) $kept[] = $link;
            }
            $cfg['columns'][$i]['links'] = $kept;
        }
        return $cfg;
    }

    public function up(): void
    {
        if (!Schema::hasTable('site_settings')) return;
        $row = SiteSetting::where('key', 'footer_config')->first();
        if (!$row) return;
        $cfg = json_decode((string) $row->value, true);
        if (!is_array($cfg)) return;
        $new = $this->strip($cfg, ['/games']);
        if ($new === $cfg) return;
        $row->update(['value' => json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        Cache::forget('site_settings_public');   // 공개 설정 캐시 (AdminSettingsController::SETTINGS_PUBLIC_CACHE_KEY)
    }

    public function down(): void
    {
        // 설정값이라 되돌릴 때 다시 넣지 않는다
    }
};
