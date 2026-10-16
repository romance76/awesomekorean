<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// T1 공동구매 할 일에 "디파짓 카드 결제 구현" 사실과 남은 일을 덧붙인다. 같은 메모가 이미 있으면 건너뜀, 데이터 삭제 없음.
return new class extends Migration
{
    private const MARK = '[10/16 디파짓 결제 구현]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $row = DB::table('admin_todos')->where('title', 'T1 공동구매 구조 변경 (참가 무료 · 오픈 수수료 · 참가자 명단)')->first();
        if (!$row || str_contains((string) $row->detail, self::MARK)) return;
        DB::table('admin_todos')->where('id', $row->id)->update([
            'detail' => (string) $row->detail . "\n\n" . self::MARK . "\n"
                . "- 열 때 디파짓 카드 결제를 만들었다(기존 카드 보류→청구 방식 재사용). 관리자·운영자는 면제.\n"
                . "- 소액: 카드를 확인하면 바로 청구되고 글이 열림. 사업자·전국: 카드는 보류만 해 두고 관리자가 승인할 때 청구, 반려·취소하면 청구 없이 보류 해제. 이미 청구된 디파짓은 환불하지 않음.\n"
                . "- 카드 확인을 마치지 않은 임시 글은 취소하면 바로, 아니면 60분 뒤 자동 정리(groupbuys:cleanup, 30분마다). 카드 보류(약 7일)가 만료되기 전(6일)에 승인 못 한 사업자 공동구매는 자동 반려(청구 없음).\n"
                . "- 결제 내역은 마이페이지 결제·인보이스와 관리자 매출('공동구매 디파짓')에 나옴.\n"
                . "- 같이 고친 것: 관리자 화면 반려가 사유 이름이 달라 항상 실패하던 문제.\n"
                . "- 아직 안 만든 것: 공동구매 완료·수락 → 총액 수수료(− 디파짓) 결제 → 참가자 정보 열람(T1 후반), 쿠폰 발급·사용.\n"
                . "- 실제 카드 결제 시험(테스트 카드)은 일반 회원 계정으로 사용자가 직접 해야 함(관리자 계정은 디파짓이 면제라 결제 창이 안 뜸).",
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
