<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 회원 등급 10단계 → 15단계 (누적 획득 포인트 lifetime_points 기준, 링 이미지 15종과 1:1).
// 등급은 즉석 계산이라 이 값만 바꾸면 전 회원에 바로 반영되고 회원 데이터는 건드리지 않는다.
// 관리자 > 포인트 설정(grade 카테고리)에서 언제든 조정 가능. 단계 이름은 MemberGrade.php 에 있음.
// 새 기준점은 모든 단계에서 기존보다 같거나 낮아 강등되는 회원이 없다.
return new class extends Migration
{
    private const NEW = [
        2 => 30, 3 => 100, 4 => 250, 5 => 500, 6 => 900, 7 => 1500, 8 => 2400, 9 => 3600, 10 => 5200,
        11 => 7500, 12 => 10500, 13 => 15000, 14 => 21000, 15 => 30000,
    ];
    private const OLD = [
        2 => 100, 3 => 300, 4 => 800, 5 => 2000, 6 => 5000, 7 => 10000, 8 => 25000, 9 => 50000, 10 => 100000,
    ];
    private const NAMES = [
        2 => '브론즈', 3 => '실버', 4 => '골드', 5 => '로즈', 6 => '자수정', 7 => '사파이어', 8 => '아쿠아 월계',
        9 => '에메랄드 월계', 10 => '스타 사파이어', 11 => '바이올렛 월계', 12 => '마젠타 월계', 13 => '루비 윙',
        14 => '블랙 골드', 15 => '레전드',
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::NEW as $lvl => $min) {
            DB::table('point_settings')->updateOrInsert(
                ['key' => 'grade_' . $lvl . '_min'],
                ['category' => 'grade', 'label' => $lvl . '단계 ' . self::NAMES[$lvl], 'value' => (string) $min,
                 'description' => '누적 획득 포인트(lifetime_points) 기준', 'created_at' => $now, 'updated_at' => $now]
            );
        }
        $this->flush();
    }

    public function down(): void
    {
        $now = now();
        foreach (self::OLD as $lvl => $min) {
            DB::table('point_settings')->where('key', 'grade_' . $lvl . '_min')->update(['value' => (string) $min, 'updated_at' => $now]);
        }
        DB::table('point_settings')->whereIn('key', ['grade_11_min', 'grade_12_min', 'grade_13_min', 'grade_14_min', 'grade_15_min'])->delete();
        $this->flush();
    }

    private function flush(): void
    {
        try { \App\Support\PointRules::flush(); } catch (\Throwable $e) {}
    }
};
