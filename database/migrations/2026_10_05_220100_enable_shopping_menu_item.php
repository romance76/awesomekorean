<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// NavBar.vue의 defaultMenus는 site_settings.menu_config가 비어있을 때만 쓰이는
// 폴백이라, 운영 DB에 이미 커스텀 menu_config가 저장돼 있으면 코드의
// enabled:false → true 변경만으로는 '쇼핑' 메뉴가 뜨지 않는다 — 기존 JSON에
// 'shopping' 항목이 있으면 enabled를 true로 바꿔주고, 아예 없으면 추가한다.
return new class extends Migration {
    public function up(): void
    {
        $setting = DB::table('site_settings')->where('key', 'menu_config')->first();
        if (!$setting || !$setting->value) return;

        $menus = json_decode($setting->value, true);
        if (!is_array($menus)) return;

        $found = false;
        foreach ($menus as &$m) {
            if (($m['key'] ?? null) === 'shopping') {
                $m['enabled'] = true;
                $found = true;
            }
        }
        unset($m);

        if (!$found) {
            $menus[] = [
                'key' => 'shopping', 'label' => '쇼핑', 'label_en' => 'Shopping', 'icon' => '🛍️',
                'path' => '/shopping', 'enabled' => true,
            ];
        }

        DB::table('site_settings')->where('key', 'menu_config')->update(['value' => json_encode($menus)]);
    }

    public function down(): void
    {
        // 메뉴 활성화 1회성 작업 — 되돌릴 필요 없음
    }
};
