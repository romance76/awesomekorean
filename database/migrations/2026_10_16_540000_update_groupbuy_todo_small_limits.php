<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// T1 공동구매 할 일에 $500 미만 소액 공구의 한도 확정 사항을 덧붙인다. 같은 메모가 이미 있으면 건너뜀, 데이터 삭제 없음.
return new class extends Migration
{
    private const MARK = '[10/16 추가 확정: 소액 한도]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $row = DB::table('admin_todos')->where('title', 'T1 공동구매 구조 변경 (참가 무료 · 오픈 수수료 · 참가자 명단)')->first();
        if (!$row || str_contains((string) $row->detail, self::MARK)) return;
        DB::table('admin_todos')->where('id', $row->id)->update([
            'detail' => (string) $row->detail . "\n\n" . self::MARK . "\n"
                . "- $500 미만 소액 공구는 한도를 먼저 제한한다: 참가 인원 최대 50명, 기간 최대 2주, 개수 최대 50개. 이 한도를 넘는 공구는 소액으로 열 수 없다.\n"
                . "- 소액 공구 수수료는 정액 $5(보증료도 $5).\n\n"
                . "아직 남은 결정:\n"
                . "1) $3,000 이상 구간 안: 3,000~3,999 5% / 4,000~4,999 6%(Claude 가 사이를 채운 값) / 5,000~9,999 7% / 10,000 이상 8%\n"
                . "2) $500 이상 공구의 디파짓(=기간별 오픈 수수료 3일 $5·1주 $7·2주 $10·1개월 $15, 환불 안 함)과 총액 수수료의 관계: 합산(완료 때 총액 수수료만 추가 결제, 총 부담 = 디파짓 + 총액 수수료) 안 vs 차감(디파짓에서 총액 수수료를 뺌) 안",
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
