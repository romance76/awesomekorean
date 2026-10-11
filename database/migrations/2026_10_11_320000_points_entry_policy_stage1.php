<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 포인트·Entry 구분 1단계 (Kay 결정 2026-10-11, qa/points_entry_ads_structure_report.md)
 *  - 포인트 판매 끔: point_settings.point_sales_enabled = 0 (없으면 만들고, 이미 있으면 0 으로)
 *  - 일반 글쓰기로 Entry 가 쌓이던 "활동 N회 Entry" 끔: entry_settings.activity_required_count = 0
 *    (Entry 는 출석·거래 완료 같은 "성과"로만. 관리자 › Entry 설정에서 다시 켤 수 있음)
 * 설정값만 바꾸고 회원 잔액·지급 기록은 건드리지 않는다. 여러 번 돌려도 안전.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = DB::getSchemaBuilder();

        if ($schema->hasTable('point_settings')) {
            $row = ['category' => 'spend', 'label' => '포인트 판매(카드 결제로 포인트 사기)', 'value' => '0',
                'description' => '0 = 판매 안 함(기본, Kay 결정 2026-10-11). 포인트는 활동으로만 모으고 기프트카드로 교환. 1 = 판매 다시 열기'];
            if (DB::table('point_settings')->where('key', 'point_sales_enabled')->exists()) {
                DB::table('point_settings')->where('key', 'point_sales_enabled')->update(['value' => '0', 'updated_at' => now()]);
            } else {
                DB::table('point_settings')->insert(array_merge($row, ['key' => 'point_sales_enabled', 'created_at' => now(), 'updated_at' => now()]));
            }
        }

        if ($schema->hasTable('entry_settings')) {
            DB::table('entry_settings')->where('key', 'activity_required_count')->update([
                'value' => '0',
                'description' => '0 = 끔(Kay 결정 2026-10-11: 일반 글쓰기로는 Entry 를 주지 않음). 숫자를 넣으면 그 횟수마다 Entry 1개',
                'updated_at' => now(),
            ]);
        }

        // 설정 캐시 비우기 (PointRules / EntrySettings)
        foreach (['entry_settings_kv', 'point_rules_kv'] as $k) Cache::forget($k);
    }

    public function down(): void
    {
        // 설정을 자동으로 되돌리지 않는다 (관리자 화면에서 바꿀 수 있음).
    }
};
