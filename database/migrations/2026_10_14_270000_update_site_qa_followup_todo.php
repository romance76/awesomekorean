<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 2026_10_14_250000 에서 올린 '사이트 점검 후속' 할 일 중, 사용자가 정해 준 항목(주소 막기·약관·더미 글·공동구매 마감 표시·글 등록 횟수 제한)을
// 처리한 것으로 적고 남은 결정만 남긴다. 같은 메모가 이미 있으면 건너뜀(여러 번 돌려도 안전), 데이터 삭제 없음.
return new class extends Migration
{
    private const MARK = '[10/14 처리 반영]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $row = DB::table('admin_todos')->where('title', '사이트 점검 후속(10/14 보고서): 메뉴에서 뺀 서비스 주소, 약관, 수집 콘텐츠, 공동구매 마감')->first();
        if (!$row || str_contains((string) $row->detail, self::MARK)) return;
        DB::table('admin_todos')->where('id', $row->id)->update([
            'title' => '사이트 점검 후속(10/14 보고서): 수집 콘텐츠 처리 방침, 검증 없는 쓰기 함수',
            'detail' => self::MARK . " 아래는 처리 완료:\n"
                . "- /games·/elder·/poker7 주소: 홈으로 이동(서버 301 + 화면 라우터) — 완료\n"
                . "- 이용약관·개인정보처리방침의 게임 문구: 제거(제24조는 '포인트 및 가상재화'로 바꿔 포인트 비현금 안내만 유지) — 완료\n"
                . "- 더미 글(커뮤니티 #90 등): 사용자 결정으로 계속 공개 — 조치 없음\n"
                . "- 공동구매: 마감일이 지나면 '마감'으로 표시(목록·상세, '마감' 필터 추가). 참여 신청은 원래 막혀 있음. DB 상태값·환불은 그대로 — 완료\n"
                . "- 글 등록 전용 횟수 제한: 회원당 분당 5회·하루 50회(관리자·운영진 제외), 커뮤니티·Q&A·구인·장터·부동산·업소·동호회·레시피·공동구매·행사·숏츠 — 완료\n\n"
                . "남은 결정·확인:\n"
                . "1) 수집·복사 콘텐츠: 장터 수집 상품(#943), 번역 Q&A(#7848), 뉴스 본문 전문(#40997), 구인 #296 연락처 공개, 업소록의 비한인 업체·영어 업종 → 처리 방침\n"
                . "2) 검증 없는 쓰기 함수(업소 정보·메뉴·사진, 이력서 수정, 쇼핑 후기, 구인 지원, 친구 신청, 행사 참석, 주식 관심종목) — 로그인 점검 때 소유자 확인과 함께 점검\n"
                . "3) 마감 지난 공동구매의 자동 종료·참여자 환불(돈 관련이라 별도 승인 필요) — 지금은 표시만 '마감'",
            'category' => '결정 필요', 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
