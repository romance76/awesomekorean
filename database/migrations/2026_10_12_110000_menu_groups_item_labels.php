<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// 커뮤니티 묶음 안에서 메뉴 '커뮤니티'를 '게시판'으로 보이게 (이미 따로 정했으면 건드리지 않음)
return new class extends Migration
{
    public function up(): void
    {
        $raw = DB::table('site_settings')->where('key', 'menu_groups')->value('value');
        $groups = $raw ? json_decode($raw, true) : null;
        if (!is_array($groups)) return;
        $changed = false;
        foreach ($groups as &$g) {
            if (($g['key'] ?? '') === 'community' && in_array('community', $g['items'] ?? [], true) && empty($g['item_labels']['community'])) {
                $g['item_labels'] = array_merge($g['item_labels'] ?? [], ['community' => '게시판']);
                $changed = true;
            }
        }
        unset($g);
        if ($changed) {
            DB::table('site_settings')->where('key', 'menu_groups')->update(['value' => json_encode($groups, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
            Cache::forget('site_settings_public');   // 공개 설정 캐시(30분) 바로 비우기
        }
    }

    public function down(): void
    {
        // 되돌릴 것 없음 (이름 표시만 바뀜)
    }
};
