<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 회원 등급 기준점 재조정 (10/9): "모든 활동을 잘해도(하루 약 45P) 에메랄드(Lv.9)까지 약 6개월" 이 되도록 전체 곡선을 늦춘다.
// 같은 날 오전에 넣은 초안(에메랄드 3,600P)은 모든 활동을 다 하면 2.5~3개월이라 너무 빨랐음.
// 등급은 즉석 계산이라 회원 데이터는 건드리지 않고, 관리자 > 포인트 설정(grade 카테고리)에서 언제든 다시 조정 가능.
return new class extends Migration
{
    private const NEW = [
        2 => 30, 3 => 150, 4 => 400, 5 => 900, 6 => 1700, 7 => 3000, 8 => 5000, 9 => 8000, 10 => 12000,
        11 => 17500, 12 => 25000, 13 => 36000, 14 => 52000, 15 => 75000,
    ];
    private const PREVIOUS = [
        2 => 30, 3 => 100, 4 => 250, 5 => 500, 6 => 900, 7 => 1500, 8 => 2400, 9 => 3600, 10 => 5200,
        11 => 7500, 12 => 10500, 13 => 15000, 14 => 21000, 15 => 30000,
    ];

    public function up(): void
    {
        $this->apply(self::NEW);
    }

    public function down(): void
    {
        $this->apply(self::PREVIOUS);
    }

    private function apply(array $values): void
    {
        $now = now();
        foreach ($values as $lvl => $min) {
            DB::table('point_settings')->where('key', 'grade_' . $lvl . '_min')->update(['value' => (string) $min, 'updated_at' => $now]);
        }
        try { \App\Support\PointRules::flush(); } catch (\Throwable $e) {}
    }
};
