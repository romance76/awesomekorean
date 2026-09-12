<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 채용확정(구인구직)·임대완료(부동산) — 그동안 관리자 화면에 항목만 있고
// 실제 상태전이 기능 자체가 없어 미착수였던 것을 신규 구축하며 함께 시드.
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $settings = [
            ['key' => 'job_hire_complete',                  'label' => '채용확정 보상(구인자)',      'value' => '30'],
            ['key' => 'job_hire_complete_daily_max',        'label' => '채용확정 하루 한도',         'value' => '5'],
            ['key' => 'realestate_rent_complete',            'label' => '임대/매매완료 보상',         'value' => '100'],
            ['key' => 'realestate_rent_complete_daily_max',  'label' => '임대/매매완료 하루 한도',    'value' => '3'],
        ];
        foreach ($settings as $s) {
            DB::table('point_settings')->updateOrInsert(
                ['key' => $s['key']],
                ['category' => 'earn', 'label' => $s['label'], 'value' => $s['value'], 'description' => '', 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('point_settings')->whereIn('key', [
            'job_hire_complete', 'job_hire_complete_daily_max',
            'realestate_rent_complete', 'realestate_rent_complete_daily_max',
        ])->delete();
    }
};
