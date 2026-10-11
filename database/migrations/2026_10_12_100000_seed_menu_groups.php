<?php

use App\Support\MenuGroups;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 상단 메뉴 묶음 기본값 — 이미 있으면 건드리지 않는다. 처음엔 꺼 둔 상태(menu_groups_enabled = 0).
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('site_settings')->where('key', 'menu_groups')->exists()) {
            DB::table('site_settings')->insert(['key' => 'menu_groups', 'value' => json_encode(MenuGroups::defaults(), JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]);
        }
        if (!DB::table('site_settings')->where('key', 'menu_groups_enabled')->exists()) {
            DB::table('site_settings')->insert(['key' => 'menu_groups_enabled', 'value' => '0', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // 설정 값만 지운다 (다른 데이터는 손대지 않음)
        DB::table('site_settings')->whereIn('key', ['menu_groups', 'menu_groups_enabled'])->delete();
    }
};
