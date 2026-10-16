<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// T1 공동구매 할 일에 보증료·$500~999 요율 확정 사항을 덧붙이고, 해결된 남은 결정을 정리한다. 같은 메모가 이미 있으면 건너뜀, 데이터 삭제 없음.
return new class extends Migration
{
    private const MARK = '[10/16 추가 확정: 보증료]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $row = DB::table('admin_todos')->where('title', 'T1 공동구매 구조 변경 (참가 무료 · 오픈 수수료 · 참가자 명단)')->first();
        if (!$row || str_contains((string) $row->detail, self::MARK)) return;
        DB::table('admin_todos')->where('id', $row->id)->update([
            'detail' => (string) $row->detail . "\n\n" . self::MARK . "\n"
                . "- $500~999 구간 요율은 2%.\n"
                . "- 보증료: 총액 $500 미만 공구는 $5, $500 이상 공구는 $10(개설 때 결제, 완료 때 수수료에서 차감).\n"
                . "- 목표 미달 후 개설자가 수락하지 않고 그대로 종료하면 보증료는 돌려주지 않음.\n"
                . "- 위 '남은 결정' 중 3)·4)·6) 해결: $500 미만 소액 정액 최소 $5 = 보증료 $5 라 차액 문제 없음(정액이 $5 를 넘으면 차액만 추가 결제), 미수락 종료 시 보증료 몰수.\n\n"
                . "아직 남은 결정:\n"
                . "1) $3,000 이상 구간의 경계와 요율(3,000~4,999 5% / 5,000~9,999 7% / 10,000 이상 8% 로 확정할지)\n"
                . "2) $500 미만 소액 정액의 종류별(인원/기간/개수) 금액\n"
                . "3) $500 이상 기간별 오픈 수수료(3일 $5·1주 $7·2주 $10·1개월 $15)를 총액 수수료에 더해서 받는지, 둘 중 큰 쪽만 받는지\n"
                . "4) 카드 결제(T4) 이후 구현",
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
