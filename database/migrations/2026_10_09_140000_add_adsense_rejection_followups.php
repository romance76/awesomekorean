<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 10/9 애드센스 1차 심사 거절("사이트가 아직 광고를 표시할 준비가 안 됨") 이후의 후속 할 일을 관리자 "할 일 목록"에 추가한다.
// - 제목이 같은 항목이 이미 있으면 건너뛴다(여러 번 돌려도 안전). 기존 항목은 지우지 않는다.
// - 기존 '애드센스 가입 신청' / '가입 + 코드 연동' 항목에는 결과 메모만 한 번 덧붙인다.
return new class extends Migration
{
    private const MARK = '[10/9 심사 결과]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;

        $now = now();

        // 1) 기존 항목에 결과 메모(한 번만)
        $note = self::MARK . ' 1차 심사 거절 메일 수신 — "사이트가 아직 광고를 표시할 준비가 안 됨". 메일에는 구체 사유가 없어 AdSense › 사이트 › awesomekorean.com 에서 확인 필요. 아래 후속 항목 참고.';
        foreach (['애드센스 가입 신청', 'Google AdSense 가입 + 코드 연동'] as $title) {
            $row = DB::table('admin_todos')->where('title', $title)->first();
            if ($row && !str_contains((string) $row->detail, self::MARK)) {
                DB::table('admin_todos')->where('id', $row->id)->update([
                    'detail' => rtrim((string) $row->detail) . "\n" . $note,
                    'updated_at' => $now,
                ]);
            }
        }

        // 2) 새 항목
        $sort = (int) DB::table('admin_todos')->max('sort_order');

        // [분류, 우선순위, 상태, 제목, 설명(+확인 방법)]
        $rows = [
            ['애드센스', 'high', 'todo', '애드센스 거절 사유 확인 (Sites 페이지)',
                "10/9 거절 메일은 '사이트가 광고 준비가 안 됨'이라고만 되어 있고 사유가 없음.\n확인: adsense.google.com › 사이트 › awesomekorean.com 옆의 '주의 필요/문제' 를 눌러 사유 이름을 확인 (예: 가치가 낮은 콘텐츠 / 복제된 콘텐츠 / 사이트 내비게이션 / 정책 위반 / 사이트 접속 불가). 사유를 Claude 에게 알려 주면 해당 항목 위주로 수정.\n주의: 고치기 전에 반복 재신청하면 심사만 길어질 수 있음."],

            ['애드센스', 'high', 'todo', '네이버 지식인에서 가져온 Q&A(약 4,400건) 처리 방침 결정',
                "복사된 콘텐츠로 거절되거나 저작권 문제가 될 가능성이 가장 큰 항목.\n선택지: (a) 검색엔진 색인 제외(noindex) + 사이트맵 제외 + 광고 제외 (b) 비공개/삭제 (c) 직접 쓴 글로 점차 교체.\n주의: qa_posts 에 '가져온 글' 표시 컬럼이 없어 구분 기준(더미 사용자, 작성일 등)부터 정해야 함. 수집 데이터 보존 규칙상 삭제는 승인 후 WHERE 조건으로만. 방침이 정해지면 Claude 와 같이 처리."],

            ['애드센스', 'medium', 'todo', '상세 페이지 본문을 서버에서 만들어 주기 (Q&A·뉴스·커뮤니티·구인 등)',
                "홈에는 10/9 크롤러용 본문(소개·메뉴 링크·최신 정보 글)을 넣었으나, 게시글 상세는 아직 자바스크립트로만 그려져 크롤러가 제목·본문을 읽기 어려움.\n해야 할 일: 정보(/info)처럼 서버에서 제목/설명/본문을 직접 출력하거나 사전 렌더링, 상세 주소별 title·description·canonical 설정.\n확인: 주소창에 view-source: 를 붙여 열었을 때 본문 글자가 보이는지, Search Console › URL 검사 › 크롤링된 페이지."],

            ['애드센스', 'medium', 'todo', '뉴스(RSS·번역) 게재 범위 점검',
                "뉴스 상세에서 본문 전체를 보여 주거나 영어 기사를 번역(EN→KO)해 올리는 경우 복사/저작권 문제가 될 수 있음.\n확인: 제목 + 짧은 요약 + 원문 링크 중심인지, 출처 표기가 있는지. 필요하면 요약 길이를 줄이고 원문 링크를 눈에 띄게.\n광고는 직접 쓴 글(정보 글) 위주 페이지에만 먼저 붙이는 것도 방법."],

            ['애드센스', 'medium', 'todo', '자동 수집 콘텐츠(숏츠·음악·매물) 페이지에는 광고 제외',
                "영상 임베드만 있는 페이지, 자동 수집한 매물/영상 목록은 애드센스 '가치 낮은 콘텐츠'로 볼 수 있음.\n광고 코드를 넣을 때 정보 글·Q&A·커뮤니티(직접 쓴 글) 위주로 넣고, 숏츠/음악/수집 매물 페이지는 제외."],

            ['애드센스', 'medium', 'todo', '더미 사용자·더미 동호회 정리 (정식 오픈 전)',
                "Q&A 가져오기 때 만든 더미 사용자(약 2,670명), 비어 있는 동호회 등 '사람이 쓰지 않은' 흔적 정리 계획.\n주의: 삭제 전에 어떤 글이 연결되어 있는지 확인하고 Kay 승인 후 진행."],

            ['애드센스', 'high', 'todo', '재신청 시점 정하기 (수정 후 한 번에)',
                "위 항목(사유 확인, Q&A 방침, 본문 서버 출력, 뉴스 범위)을 정리한 뒤 한 번에 재신청. 심사는 보통 며칠~2주.\n재신청 전 체크: 직접 쓴 정보 글 수, 이용자가 올린 글 수, 소개/문의/개인정보/약관 노출, 모바일 화면, 준비중 문구 없음."],

            ['애드센스', 'low', 'done', '크롤러용 홈 본문(서버 렌더링 대체 내용) 추가',
                "10/9 welcome 화면의 #app 안에 사이트 소개, 주요 메뉴 링크, 최신 정보 글 12개를 서버에서 미리 출력. 앱이 뜨면 앱 화면으로 교체됨. 홈 이외 화면은 위 '상세 페이지 본문 서버 출력' 항목으로 이어서."],
        ];

        foreach ($rows as [$cat, $pri, $st, $title, $detail]) {
            if (DB::table('admin_todos')->where('title', $title)->exists()) continue;
            $sort += 10;
            DB::table('admin_todos')->insert([
                'title' => $title, 'detail' => $detail, 'category' => $cat, 'priority' => $pri, 'status' => $st,
                'sort_order' => $sort, 'done_at' => $st === 'done' ? $now : null, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // 사용자가 상태/내용을 고쳤을 수 있어 되돌릴 때 삭제하지 않는다
    }
};
