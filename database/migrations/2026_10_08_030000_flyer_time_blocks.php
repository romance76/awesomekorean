<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * NEW 전면광고 시간 선택을 "한 시간씩"이 아니라 몇 시간씩 묶은 칸으로.
 *  - 새벽(자정~오전 6시): 3시간씩 두 칸, 피크: 오전 11시~오후 6시 (여성 이용자가 많은 시간)
 *  - 칸 경계 / 피크 시작·끝 / 새벽 끝 시각은 관리자가 가격/할인 센터에서 조정
 * 저장은 그대로 시간 단위 슬롯이라 기존 예약과 호환된다.
 */
return new class extends Migration {
    public function up(): void
    {
        $new = [
            ['flyer_block_edges',     '전면광고 시간 칸 경계 (시각을 쉼표로, 0으로 시작해 24로 끝)', '0,3,6,9,11,14,18,21,24', '예: 0,3,6,9,11,14,18,21,24 → 자정~3시 / 3~6시 / 6~9시 / 9~11시 / 11~2시 / 2~6시 / 6~9시 / 9시~자정'],
            ['flyer_peak_start_hour', '전면광고 피크 시작 시각 (0~23)', '11', '피크 가격이 적용되는 시작 시각(포함). 기본 11 = 오전 11시'],
            ['flyer_peak_end_hour',   '전면광고 피크 끝 시각 (1~24)', '18', '피크 가격이 끝나는 시각(미포함). 기본 18 = 오후 6시'],
            ['flyer_night_end_hour',  '전면광고 새벽(저렴) 끝 시각', '6', '자정부터 이 시각 전까지 새벽 가격. 기본 6 = 오전 6시'],
        ];
        foreach ($new as [$key, $label, $value, $desc]) {
            if (!DB::table('point_settings')->where('key', $key)->exists()) {
                DB::table('point_settings')->insert([
                    'category' => 'spend', 'key' => $key, 'label' => $label, 'value' => $value,
                    'description' => $desc, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        DB::table('point_settings')->where('key', 'flyer_peak_pct')->update([
            'label' => '전면광고 피크 시간대 가격 (%)', 'description' => '피크(기본 오전 11시~오후 6시) 가격 = 기본 가격 × 이 값 ÷ 100', 'updated_at' => now()]);
        DB::table('point_settings')->where('key', 'flyer_night_pct')->update([
            'label' => '전면광고 새벽 시간대 가격 (%)', 'description' => '새벽(자정~기본 오전 6시) 가격 = 기본 가격 × 이 값 ÷ 100', 'updated_at' => now()]);
    }

    public function down(): void {}
};
