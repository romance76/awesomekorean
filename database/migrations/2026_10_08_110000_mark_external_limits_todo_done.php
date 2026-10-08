<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// "외부 서비스 요금제·한도 확인" 항목에 점검 결과를 남기고 완료 처리한다.
// 이미 사람이 완료했거나 같은 메모가 있으면 건드리지 않는다.
return new class extends Migration
{
    private const MARK = '[10/7 확인]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;

        $row = DB::table('admin_todos')->where('title', '외부 서비스 요금제·한도 확인')->first();
        if (!$row || $row->status === 'done' || str_contains((string) $row->detail, self::MARK)) return;

        $note = 'YouTube: 하루 약 320건으로 한도 대비 낮음. RealtyAPI: 무료 플랜 월 250건 중 48건 사용(하루 8건 → 월 248건으로 여유 2건)이라 우편번호를 4곳→3곳으로 줄여 하루 6건(월 약 186건)으로 조정. '
              . 'eBay: 하루 한 번 수집(대략 수십~400건 추정)이라 기본 한도(약 5,000건) 대비 낮음 — 개발자 사이트에 사용량 화면이 없어 코드 기준 추정. 구글 Places: 결제 꺼짐으로 동작 안 함(비용 0).';

        DB::table('admin_todos')->where('id', $row->id)->update([
            'detail' => rtrim((string) $row->detail) . "\n" . self::MARK . ' ' . $note,
            'status' => 'done',
            'done_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // 진행 상황 기록이라 되돌리지 않음
    }
};
