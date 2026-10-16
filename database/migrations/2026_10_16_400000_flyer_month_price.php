<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * NEW 전면광고 가격: 주(State)별 차등 + 관리자가 정하는 "기준가".
 *  - 전국 광고는 더 이상 팔지 않는다. 기준가(flyer_national_month_usd, 기본 $6,000)는 "모든 주를 24시간 × 30일 정가로 다 팔았을 때의 한 달 합계"이고,
 *    주별 가격을 이 금액 안에서 나누는 기준으로만 쓴다.
 *  - 한인이 많은 상위 약 20개 주는 4단계로 나누고, 나머지 30곳(주+DC)은 아주 싸게. 1단계를 100%로 본 상대 가격:
 *    1단계 100% · 2단계 40% · 3단계 25% · 4단계 15% · 그 밖의 주 2.8%  (값은 1000분율(‰): 1000 / 400 / 250 / 150 / 28)
 *    → 기본값 기준 한 주 한 달: 1단계 약 $600, 2단계 약 $240, 3단계 약 $150, 4단계 약 $90, 그 밖 약 $17 (모두 더하면 $6,000)
 *  - 단계별 주 목록·상대 비율·기준가는 모두 관리자 가격 설정에서 바꾼다. 시간당 가격은 이 한 달 금액을 피크·새벽 배율로 나눠 자동 계산.
 *  - 추가 주는 기존 '추가 주 가격(%)'만큼 (그 주 자신의 가격 기준).
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['flyer_national_month_usd', '전면광고 기준가 ($) — 모든 주를 24시간 × 30일 정가로 다 팔았을 때의 한 달 합계 (전국 광고는 팔지 않음)', '6000',
                '주별 가격은 이 기준가를 51곳의 상대 비율로 나눈 몫이에요. 이 금액을 바꾸면 모든 주 가격이 같은 비율로 바뀌어요. 0 이면 예전 시간당 가격 설정을 써요.'],
            ['flyer_tier1_permille', '전면광고 1단계 주 상대 가격 (‰, 1단계 = 1000 기준)', '1000', '가장 비싼 단계. 다른 단계는 이 값에 대한 비율(‰)로 정해요'],
            ['flyer_tier2_permille', '전면광고 2단계 주 상대 가격 (‰)', '400', '400 → 1단계의 40%'],
            ['flyer_tier3_permille', '전면광고 3단계 주 상대 가격 (‰)', '250', '250 → 1단계의 25%'],
            ['flyer_tier4_permille', '전면광고 4단계 주 상대 가격 (‰)', '150', '150 → 1단계의 15%'],
            ['flyer_other_permille', '전면광고 그 밖의 주 상대 가격 (‰)', '28', '1~4단계에 없는 주(나머지 30곳). 28 → 1단계의 2.8%'],
            ['flyer_tier1_states', '전면광고 1단계 주 목록 (쉼표로)', 'CA,NY,NJ,TX,VA', '한인이 가장 많은 주. 주 약어를 쉼표로 (예: CA,NY,NJ,TX,VA)'],
            ['flyer_tier2_states', '전면광고 2단계 주 목록 (쉼표로)', 'WA,IL,GA,MD,FL', '주 약어를 쉼표로'],
            ['flyer_tier3_states', '전면광고 3단계 주 목록 (쉼표로)', 'PA,MA,HI,CO,AZ', '주 약어를 쉼표로'],
            ['flyer_tier4_states', '전면광고 4단계 주 목록 (쉼표로)', 'NV,NC,SC,OH,OR,MI', '주 약어를 쉼표로. 1~4단계에 없는 주는 "그 밖의 주" 가격'],
        ];
        foreach ($rows as [$key, $label, $value, $desc]) {
            if (!DB::table('point_settings')->where('key', $key)->exists()) {
                DB::table('point_settings')->insert([
                    'category' => 'spend', 'key' => $key, 'label' => $label, 'value' => $value,
                    'description' => $desc, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        // 예전 시간당 가격 칸은 기준가가 0 일 때만 쓰인다는 안내
        DB::table('point_settings')->where('key', 'flyer_price_national')->update(['label' => '(예전 방식) 전면광고 시간당 가격 · 전국 (센트¢) — 기준가가 0 일 때만 사용', 'updated_at' => now()]);
        DB::table('point_settings')->where('key', 'flyer_price_state')->update(['label' => '(예전 방식) 전면광고 시간당 가격 · 주 (센트¢) — 기준가가 0 일 때만 사용', 'updated_at' => now()]);
    }

    public function down(): void {}
};
