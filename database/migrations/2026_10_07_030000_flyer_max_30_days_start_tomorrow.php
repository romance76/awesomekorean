<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 전단 광고: 1회 최대 30일, 시작일은 내일부터.
 * 이전에 기본값(14)으로 들어간 설정만 30으로 올리고(관리자가 바꾼 값은 건드리지 않음) 설명을 갱신.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('point_settings')->where('key', 'flyer_max_days')->where('value', '14')->update(['value' => '30', 'updated_at' => now()]);
        DB::table('point_settings')->where('key', 'flyer_max_days')->update(['description' => '한 번에 최대 며칠까지 신청할 수 있는지 (기본 30일)', 'updated_at' => now()]);
        DB::table('point_settings')->where('key', 'flyer_window_days')->update([
            'label' => '전단 광고 시작일 예약 가능 기간(일)',
            'description' => '시작일을 오늘부터 며칠 앞까지 고를 수 있는지 (시작일은 항상 내일부터)',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // no-op
    }
};
