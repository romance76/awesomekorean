<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 사이트 점검 후속 할 일(270000 에서 제목을 바꿔 둔 것)에 수집 콘텐츠 처리 결과를 반영한다. 같은 메모가 이미 있으면 건너뜀, 데이터 삭제 없음.
return new class extends Migration
{
    private const MARK = '[수집 콘텐츠 처리 반영]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $row = DB::table('admin_todos')->where('title', '사이트 점검 후속(10/14 보고서): 수집 콘텐츠 처리 방침, 검증 없는 쓰기 함수')->first();
        if (!$row || str_contains((string) $row->detail, self::MARK)) return;
        DB::table('admin_todos')->where('id', $row->id)->update([
            'title' => '사이트 점검 후속: 업소록 영어 이름 업체 확인, 검증 없는 쓰기 함수 점검',
            'detail' => self::MARK . " 사용자 결정에 따라 처리:\n"
                . "- 중고장터(eBay 615건)·부동산(realtor.com 332건): 목록에 'eBay 상품'/'외부 매물' 표시와 안내 문구 추가, 구글 검색 색인 제외(noindex). 회원 글이 쌓이면 SeoHead::NOINDEX 에서 market·realestate 를 빼서 색인을 다시 연다 — 완료\n"
                . "- 뉴스: 현행 유지(앞 200자 요약 + '본문 일부만 표시' 안내 + 원문 링크 — 전문이 아니었음) — 조치 없음\n"
                . "- 업소록: 업종 영어 코드(etc·medical 등)를 한글로 표시, 한인이 아닌 확실한 업체 24곳(대형 체인·타 민족 상점)은 숨김(hidden_at, 삭제 아님) — 완료\n"
                . "- Q&A 1,000건·지식인: 사용자 결정으로 일단 그대로 둠\n\n"
                . "남은 것:\n"
                . "1) 업소록에서 한인인지 애매한 업체 확인: Japanese Auto Service(#2395), Hair By Georgia(#986), Asian market(#14149, Aurora CO), KAYAN SPICE ASIAN MARKET(#14032), Lam's Seafood Asian Market(#669). 그리고 영어 이름만 있는 약 470곳은 규칙으로 가려낼 수 없어 사람이 봐야 함\n"
                . "2) 검증 없는 쓰기 함수(업소 정보·메뉴·사진, 이력서 수정, 쇼핑 후기, 구인 지원, 친구 신청, 행사 참석, 주식 관심종목) — 로그인 점검 때 소유자 확인과 함께 점검\n"
                . "3) 마감 지난 공동구매의 자동 종료·참여자 환불(돈 관련이라 별도 승인 필요)",
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
