<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// NavBar.vue의 defaultMenus는 site_settings.menu_config가 비어있을 때만 쓰이는
// 폴백이라, 운영 DB에 이미 커스텀 menu_config가 저장돼 있으면 코드만 고쳐서는
// '정보' 메뉴가 뜨지 않는다 — 기존 menu_config JSON에 'info' 항목이 없으면 추가.
return new class extends Migration {
    public function up(): void
    {
        $setting = DB::table('site_settings')->where('key', 'menu_config')->first();
        if (!$setting || !$setting->value) return;

        $menus = json_decode($setting->value, true);
        if (!is_array($menus)) return;
        if (collect($menus)->contains(fn($m) => ($m['key'] ?? null) === 'info')) return;

        $newsIndex = collect($menus)->search(fn($m) => ($m['key'] ?? null) === 'news');
        $infoItem = [
            'key' => 'info', 'label' => '정보', 'label_en' => 'Info', 'icon' => '📘',
            'path' => '/info', 'enabled' => true, 'external' => true,
        ];

        if ($newsIndex !== false) {
            array_splice($menus, $newsIndex + 1, 0, [$infoItem]);
        } else {
            $menus[] = $infoItem;
        }

        DB::table('site_settings')->where('key', 'menu_config')->update(['value' => json_encode($menus)]);
    }

    public function down(): void
    {
        // 메뉴 추가 1회성 작업 — 되돌릴 필요 없음
    }
};
