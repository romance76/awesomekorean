<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NEW 전면광고: 내 주 + 추가 주(최대 3개) 선택.
 *  - 추가 주는 같은 시간 칸을 그 주에서도 함께 사는 것 — flyer_slots 에 주마다 행이 생기고, 이중 예약은 기존 UNIQUE 가 막는다.
 *  - 가격: 내 주 가격 + (추가 주 가격 × flyer_extra_state_pct %). 합계가 전국 가격 이상이면 전국으로 신청하도록 안내.
 *  - flyer_ads.extra_states 에 추가한 주 코드 목록(JSON)을 보관. 기존 신청은 null(= 추가 주 없음).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('flyer_ads', 'extra_states')) {
            Schema::table('flyer_ads', function (Blueprint $table) {
                $table->json('extra_states')->nullable()->after('region_key');
            });
        }
        $settings = [
            ['flyer_extra_state_pct',  '전면광고 추가 주 가격 (%)', '50', '내 주 외에 주를 추가할 때, 추가한 주 1개당 그 주 기본 가격의 몇 %를 더 받을지. 기본 50 = 절반'],
            ['flyer_max_extra_states', '전면광고 추가 주 최대 개수', '3', '내 주 외에 한 번에 더할 수 있는 주의 최대 개수 (전국이 더 저렴해지면 전국을 안내)'],
        ];
        foreach ($settings as [$key, $label, $value, $desc]) {
            if (!DB::table('point_settings')->where('key', $key)->exists()) {
                DB::table('point_settings')->insert([
                    'category' => 'spend', 'key' => $key, 'label' => $label, 'value' => $value,
                    'description' => $desc, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void {}
};
