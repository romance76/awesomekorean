<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 2026-10-14 점검 7차: 공개 DNS·옛 사이트·정보 글 전체를 읽기 전용으로 점검한 결과와, 광고 제외·사이트맵·검색 태그 작업을 할 일 목록에 반영한다.
// 제목이 같은(LIKE) 항목만 대상, 메모에 표시가 있으면 건너뜀(여러 번 돌려도 안전), 데이터 삭제 없음.
return new class extends Migration
{
    private const MARK = '[10/14 점검7]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $now = now();

        // [제목 LIKE, 완료 여부, 메모]
        $items = [
            ['옛 info.awesomekorean.com 을 새 주소로 301 이동%', true, '라이브 확인 완료: info.awesomekorean.com 의 모든 주소가 301 로 이동하고, /articles/<제목> 은 새 사이트의 같은 글(/info/<제목>)로 글 단위 이동, 그 밖의 주소는 /info 로 이동함. 서버 설정은 이미 적용돼 있었음. 남은 것: 구글 서치콘솔의 옛 속성(info.)에서 주소 변경 도구(선택).'],
            ['자동 수집 콘텐츠(숏츠·음악·매물) 페이지에는 광고 제외%', true, '적용: 애드센스 광고 코드(adsbygoogle.js)를 숏츠·음악·중고장터·부동산과, 처리 방침이 정해지지 않은 지식인 Q&A·번역 뉴스, 로그인·관리자·개인 화면에서는 아예 불러오지 않음(App\Support\SeoHead::noAdsSections). 사이트 소유 확인 태그는 모든 화면에 그대로. 방침이 정해져 광고를 켜도 되면 그 목록에서 빼면 됨.'],
            ['SPF/DKIM 에 실제 발신 서비스%', false, '공개 DNS 확인 결과: Resend DKIM(resend._domainkey) 있음, 발신 전용 주소 send.awesomekorean.com 에 자체 SPF·반송 설정 있음, DMARC(p=quarantine) 있음. 루트 SPF 는 ImprovMX(수신 전달)만 있지만 Resend 는 send. 주소로 보내서 문제 없는 구성. 남은 것(사람): 실제로 가입 인증 메일을 Gmail 로 받아 "메일 원본 보기"에서 SPF/DKIM/DMARC 가 모두 PASS 인지 확인.'],
            ['정보 글 품질 점검%', false, '공개 화면 232편 점검(qa/INFO_AUDIT_2026-10-14.md): 너무 짧은 글 0편(중간값 2,424자), 출처 링크가 하나도 없는 글 54편(23%) ← 가장 먼저 손볼 것, 주제가 겹치는 글은 H-1B 수수료 3편·분기별 추정세 2편·메디케어 Part B 2편·비자 공고 2편, "확인일" 표시가 있는 글은 23편. 글 수정·삭제는 승인 후.'],
            ['AI 생성 글 검수 방침%', false, '참고 자료: 정보 글 232편 중 출처 링크가 없는 54편의 목록이 qa/INFO_AUDIT_2026-10-14.md 에 있음. 세금·보험·비자 글부터 공식 기관 링크를 붙이고 "최종 확인일"을 적는 것을 권장.'],
            ['구글에 색인된 페이지 수 확인%', false, '이번에 한 일(10/14): 사이트맵에 공개 메뉴 목록 10곳 추가, 검색용 제목·설명·키워드·canonical·공유 미리보기를 사이트에 실제 적용(전에는 설정만 저장되고 반영 안 됨), 메뉴별 고유 제목, 로그인·관리자 화면 검색 제외. 남은 것(사람): 서치콘솔 URL 검사로 홈 주소 "색인 생성 요청" + 색인 현황 확인.'],
        ];
        foreach ($items as [$like, $done, $note]) {
            foreach (DB::table('admin_todos')->where('title', 'like', $like)->get(['id', 'detail', 'status']) as $r) {
                if (str_contains((string) $r->detail, self::MARK)) continue;
                $u = ['updated_at' => $now, 'detail' => trim(($r->detail ?? '') . "\n" . self::MARK . ' ' . $note)];
                if ($done && $r->status !== 'done') { $u['status'] = 'done'; $u['done_at'] = $now; }
                DB::table('admin_todos')->where('id', $r->id)->update($u);
            }
        }

        // 기록용 완료 항목 (이번에 한 검색 노출 작업)
        $title = '검색용 제목·설명·키워드를 사이트에 연결 + 사이트맵 보강 + 메뉴별 제목';
        if (!DB::table('admin_todos')->where('title', $title)->exists()) {
            DB::table('admin_todos')->insert([
                'title' => $title, 'category' => '애드센스', 'priority' => 'high', 'status' => 'done', 'sort_order' => (int) DB::table('admin_todos')->max('sort_order') + 10,
                'detail' => "관리자 사이트 설정의 SEO 제목·설명·메타 키워드가 저장만 되고 사이트에 반영되지 않던 것을 연결(App\\Support\\SeoHead). 비어 있던 칸에는 기본 문구를 채움. 메뉴별 고유 제목·설명, canonical, 공유 미리보기(OG/Twitter), 홈 구조화 데이터, 구글·네이버 인증 태그 지원, 로그인·관리자·개인 화면 noindex, 사이트맵에 공개 메뉴 10곳 추가.\n" . self::MARK . ' 라이브 확인 완료.',
                'done_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
