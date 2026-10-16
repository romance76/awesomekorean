<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// T1 공동구매 할 일에 수수료 표 확정본(10/16)을 덧붙인다. 같은 메모가 이미 있으면 건너뜀, 데이터 삭제 없음.
return new class extends Migration
{
    private const MARK = '[10/16 수수료 표 확정]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $row = DB::table('admin_todos')->where('title', 'T1 공동구매 구조 변경 (참가 무료 · 오픈 수수료 · 참가자 명단)')->first();
        if (!$row || str_contains((string) $row->detail, self::MARK)) return;
        DB::table('admin_todos')->where('id', $row->id)->update([
            'detail' => (string) $row->detail . "\n\n" . self::MARK . "\n"
                . "총액 수수료율(구간제, 총액 = 종료 시점 인원 × 그 인원 구간의 표시 단가):\n"
                . "- $500 미만: 정액 $5 (한도: 50명·2주·50개)\n"
                . "- $500~999: 2% / $1,000~1,999: 2% / $2,000~2,999: 3.5%\n"
                . "- $3,000~3,999: 5% / $4,000~4,999: 6% / $5,000~9,999: 7% / $10,000 이상: 8%\n\n"
                . "디파짓(열 때 결제, 환불 안 함): $500 미만 $5, $500 이상은 기간별 오픈 수수료 — 3일 $5 / 1주 $7 / 2주 $10 / 1개월 $15.\n"
                . "완료 때 결제액 = 총액 수수료 − 디파짓 (0 미만이면 0). 디파짓이 더 커도 남는 금액은 환불하지 않음(디파짓은 공구를 연 값).\n"
                . "- 예) 1개월(디파짓 $15), $750 → 총액 수수료 $15 → 완료 때 추가 $0. 1개월, $3,000 → 수수료 $150 → 추가 $135.\n"
                . "- 결제를 마쳐야 참가자 정보 열람 가능. 미수락 종료·철회 시 디파짓 환불 없음.\n"
                . "- 남은 일: 카드 결제 시험(T4) 이후 구현.",
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
