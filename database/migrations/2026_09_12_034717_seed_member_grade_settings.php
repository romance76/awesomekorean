<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 회원 등급 10단계 기준점(누적 획득 포인트 lifetime_points 기준).
// point_settings에 시드해 AdminPointSettings.vue에서 프론트 변경 없이
// 바로 조정 가능. 1단계(새싹)는 항상 0P 고정이라 별도 키 없음.
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $tiers = [
            ['key' => 'grade_2_min',  'label' => '2단계 초보',       'value' => '100'],
            ['key' => 'grade_3_min',  'label' => '3단계 일반회원',    'value' => '300'],
            ['key' => 'grade_4_min',  'label' => '4단계 활동회원',    'value' => '800'],
            ['key' => 'grade_5_min',  'label' => '5단계 우수회원',    'value' => '2000'],
            ['key' => 'grade_6_min',  'label' => '6단계 인기회원',    'value' => '5000'],
            ['key' => 'grade_7_min',  'label' => '7단계 베테랑',      'value' => '10000'],
            ['key' => 'grade_8_min',  'label' => '8단계 마스터',      'value' => '25000'],
            ['key' => 'grade_9_min',  'label' => '9단계 레전드',      'value' => '50000'],
            ['key' => 'grade_10_min', 'label' => '10단계 명예의 전당', 'value' => '100000'],
        ];
        foreach ($tiers as $t) {
            DB::table('point_settings')->updateOrInsert(
                ['key' => $t['key']],
                ['category' => 'grade', 'label' => $t['label'], 'value' => $t['value'], 'description' => '누적 획득 포인트(lifetime_points) 기준', 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('point_settings')->where('category', 'grade')->delete();
    }
};
