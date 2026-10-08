<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 10/8 처리한 항목을 할 일 목록에서 지우고, 점검 중 새로 나온 후속 항목을 추가한다.
// 제목이 같은 항목만 대상으로 하고, 없으면 건너뛴다(사람이 이미 지웠거나 고쳤을 수 있음).
return new class extends Migration
{
    private const MARK = '[10/8 점검]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;

        // 1) 처리 끝난 항목 삭제
        DB::table('admin_todos')->whereIn('title', [
            '임시 점검 주소 정리 (mail-debug 등)',
            'firebase 라이브러리 보안 권고 4건',
            'info.awesomekorean.com 글 130+개 이전',
            '운영진(moderator) 권한 범위 점검',
            '사이트 전체 점검: 깨진 링크 · 404 · 준비중 문구 · 모바일',
        ])->delete();

        $now = now();

        // 2) 정보 글 품질 점검: 결과 기록 후 진행 중으로
        $row = DB::table('admin_todos')->where('title', '정보 글 품질 점검 (얇은 글 / 중복 / 출처)')->first();
        if ($row && !str_contains((string) $row->detail, self::MARK)) {
            $note = '181편 점검 결과 — 얇은 글(1,500자 미만) 0편. 출처/참고 링크가 없는 글 13편(조지아 정착 가이드류: 자동차 보험·부동산 구입·렌트·운전면허·전기세·이사 등)은 공식 링크를 보강할 것. '
                . "\n같은 주제가 여러 편인 묶음(합치거나 하나만 남길지 결정 필요): H-1B 10만 달러 수수료 3편 / 겨울 한파·폭설 대비 4편 / 스팸·로보콜 3편 / EIN 3편 / 자동차 리스 vs 구매 2~3편 / 세금 신고 연장(10/15) 3편 / "
                . 'LLC 설립 2편 / 커뮤니티칼리지 편입 2편 / 자영업·프리랜서 분기별 추정세 2편 / 메디케어 Part B 인상 2편 / 2027 ACA 오픈 인롤먼트 2~3편 / 연방정부 셧다운 2편 / 자녀 공립학교 등록 2편 / 신용점수 쌓기 2편 / 휴대폰 요금제 2편. '
                . "\n삭제하면 이미 색인된 주소가 404가 되므로, 지울 글은 남길 글로 301 이동하는 방식을 권장. 작성일은 있으나 최종 확인일 표기는 새 글 10편에만 있음(기존 글 대부분 없음).";
            DB::table('admin_todos')->where('id', $row->id)->update([
                'detail' => rtrim((string) $row->detail) . "\n" . self::MARK . ' ' . $note,
                'status' => $row->status === 'done' ? 'done' : 'doing',
                'updated_at' => $now,
            ]);
        }

        // 3) 후속 항목 추가
        $sort = (int) DB::table('admin_todos')->max('sort_order');
        $new = [
            ['서버', 'high', 'todo', '옛 info.awesomekorean.com 을 새 주소로 301 이동',
                "옛 사이트가 아직 글 132편을 그대로 공개 중이라 같은 글이 두 주소에 있음(중복 콘텐츠 → 검색·애드센스에 불리). 글은 전부 awesomekorean.com/info 로 이전 완료(10/8).\n"
                . "할 일: 서버(nginx 등)에서 info.awesomekorean.com/articles/<제목> → awesomekorean.com/info/<제목> 으로 301 이동 설정. 제목 주소는 거의 같고 특수문자만 다른 글이 있으니, 안 맞는 글은 /info 로 보냄.\n"
                . '확인: 옛 주소를 열면 새 주소로 넘어감. 구글 서치콘솔의 옛 속성(info.)에서 주소 변경 도구 사용 가능.'],
        ];
        foreach ($new as [$cat, $pri, $st, $title, $detail]) {
            if (DB::table('admin_todos')->where('title', $title)->exists()) continue;
            $sort += 10;
            DB::table('admin_todos')->insert([
                'title' => $title, 'detail' => $detail, 'category' => $cat, 'priority' => $pri, 'status' => $st,
                'sort_order' => $sort, 'done_at' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // 진행 상황 기록이라 되돌리지 않음
    }
};
