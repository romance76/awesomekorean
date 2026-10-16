<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// T1 공동구매 할 일에 범위(기본/전국)·수령 방식·디파짓 배수 결정을 덧붙인다. 같은 메모가 이미 있으면 건너뜀, 데이터 삭제 없음.
return new class extends Migration
{
    private const MARK = '[10/16 범위·수령 방식 확정]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $row = DB::table('admin_todos')->where('title', 'T1 공동구매 구조 변경 (참가 무료 · 오픈 수수료 · 참가자 명단)')->first();
        if (!$row || str_contains((string) $row->detail, self::MARK)) return;
        DB::table('admin_todos')->where('id', $row->id)->update([
            'detail' => (string) $row->detail . "\n\n" . self::MARK . "\n"
                . "- 범위는 두 가지: ① 기본 = 작성자가 속한 주 + 육지로 맞닿은 인접 주(자동), ② 전국.\n"
                . "- 전국은 총액과 상관없이 항상 사업자 공동구매(사업자 등록증 + 관리자 승인, 마감 1개월 이내)이고 디파짓은 ×3.\n"
                . "- 수령 방식: 택배용 / 쿠폰용 중 하나를 고른다(범위와 따로 고름).\n"
                . "- 디파짓(안내용, 결제 기능 전까지 받지 않음): 소액 $5 / 사업자 3일 $5·1주 $7·2주 $10·1개월 $15 / 전국은 위의 3배(3일 $15·1주 $21·2주 $30·1개월 $45). 환불 안 함.\n"
                . "- 인접 주 목록: database/data/us_state_adjacency.json(서버·화면 공용). 알래스카·하와이는 맞닿은 주가 없어 자기 주만, 네 모서리만 닿는 주(AZ-CO, NM-UT)는 인접으로 보지 않음.\n"
                . "- 목록 보기: 내 지역(내 주 + 인접 주 + 전국) / 전국만 / 전체. 로그인 회원의 주가 있으면 '내 지역 + 전국'이 기본.\n"
                . "- 아직 안 정한 것: 전국 공동구매의 총액 수수료율을 지역과 같은 표로 할지, 택배용 전국 공구의 배송비·환불 책임 문구(약관), 쿠폰용 전국 공구(온라인 쿠폰)의 사용 방식.",
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
