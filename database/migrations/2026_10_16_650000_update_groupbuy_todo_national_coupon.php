<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// T1 공동구매 할 일에 전국=쿠폰용만·수수료율 동일·택배 책임 결정을 덧붙이고, "쿠폰 사용 방식 설계" 할 일을 새로 올린다. 같은 메모·제목이 이미 있으면 건너뜀, 데이터 삭제 없음.
return new class extends Migration
{
    private const MARK = '[10/16 전국·택배 결정]';
    private const NEW_TITLE = '공동구매 쿠폰 사용 방식 설계 (전국 쿠폰형)';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;

        $row = DB::table('admin_todos')->where('title', 'T1 공동구매 구조 변경 (참가 무료 · 오픈 수수료 · 참가자 명단)')->first();
        if ($row && !str_contains((string) $row->detail, self::MARK)) {
            DB::table('admin_todos')->where('id', $row->id)->update([
                'detail' => (string) $row->detail . "\n\n" . self::MARK . "\n"
                    . "- 전국 공동구매의 총액 수수료율은 지역(기본)과 같은 표를 쓴다(디파짓만 ×3).\n"
                    . "- 택배·배송비·교환·반품·환불·세금·분쟁은 전부 공동구매를 올린 개설자 책임. 이용약관 제26조의2 에 문구 추가함.\n"
                    . "- 전국 공동구매는 우선 쿠폰용만 열 수 있다(택배용 전국은 아직 열지 않음). 등록 화면·서버 모두 막음. 택배용은 기본(내 주 + 인접 주) 범위로만.\n"
                    . "- 다음: 쿠폰 사용 방식 설계(아래 별도 할 일).",
                'updated_at' => now(),
            ]);
        }

        if (!DB::table('admin_todos')->where('title', self::NEW_TITLE)->exists()) {
            $now = now();
            DB::table('admin_todos')->insert([
                'title' => self::NEW_TITLE, 'category' => '결정 필요', 'priority' => 'high', 'status' => 'todo',
                'sort_order' => (int) DB::table('admin_todos')->max('sort_order') + 10, 'done_at' => null, 'created_at' => $now, 'updated_at' => $now,
                'detail' => "전국 공동구매는 우선 쿠폰용만 열 수 있게 했다(2026-10-16). 쿠폰을 참가자가 어떻게 받고 어떻게 쓰는지 정해야 한다.\n"
                    . "생각해 볼 것:\n"
                    . "1) 쿠폰 발급: 공동구매가 완료·수락되면 참가자에게 쿠폰(코드/QR)이 자동 발급되는지, 개설자가 직접 보내는지\n"
                    . "2) 사용처: 매장 방문(오프라인) / 온라인 코드 입력 / 둘 다. 전국 쿠폰이라면 온라인 사용 또는 여러 지점 사용이 필요\n"
                    . "3) 사용 확인: 개설자 쪽 사용 처리(QR 스캔, 코드 입력)와 중복 사용 방지, 사용 내역을 개설자·참가자 모두 확인\n"
                    . "4) 유효기간: 발급일 기준 며칠/몇 개월, 만료 쿠폰 처리와 연장\n"
                    . "5) 환불·분쟁: 쓰지 않은 쿠폰의 환불 규칙(개설자 책임), 개설자가 문 닫거나 사라진 경우\n"
                    . "6) 정보 제공과의 관계: 참가자 이름·전화·이메일은 완료·수락 + 수수료 결제 후에 개설자에게 제공(T1). 쿠폰 사용 흐름과 순서를 맞춰야 함\n"
                    . "7) 결제: 카드 결제 시험(T4) 이후 구현",
            ]);
        }
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
