<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 2026-10-11 3차 작업(관리자 목록 가볍게, IP 차단 기간·대역, 댓글 대상 검증, 경품 조회 정리, 북마크 표시, 채팅 입장 속도)을 할 일 목록에 반영한다.
// 여러 번 돌려도 안전(메모에 표시가 있으면 건너뜀), 데이터 삭제 없음.
return new class extends Migration
{
    private const MARK = '[10/11 3차]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;

        $items = [
            ['관리자 목록 API 가벼운 버전%', 'done', '레시피 보상 후보를 DB 에서 바로 거르게 바꾸고(전체 읽기 제거), 배너 목록은 진행 중 광고 전부 + 끝난 광고는 최근 90일·최대 1000건으로 제한. (배너 화면 자체 페이지 넘기기는 아직 — 데이터가 훨씬 늘면 추가)'],
            ['회원 일괄 작업·검색 확장%', 'doing', 'IP 차단 기간(1·7·30·90일·영구)과 대역(CIDR, 예 1.2.3.0/24) 지원 추가, 내 IP 가 들어 있으면 차단 거절. 남은 것: 일괄 등급 변경.'],
            ['운영자·관리자 권한 세부 정리%', 'done', '경품 당첨 조회가 매번 데이터를 만들던 것을 최초 1회로 줄임(평소엔 매분 도는 알림 작업이 만듦). 채팅 영구제명 운영진·본인 보호, Entry 거래 이메일 최고관리자만은 앞서 완료.'],
            ['글·댓글·쪽지에 HTML 저장 방지%', 'done', '없는 글·다른 글의 댓글에는 댓글을 달 수 없게 하고, 답글의 부모 댓글이 같은 글 것인지 확인. HTML 태그 저장 방지와 쪽지 금칙어 필터는 앞서 완료.'],
        ];

        foreach ($items as [$like, $status, $note]) {
            $rows = DB::table('admin_todos')->where('title', 'like', $like)->get(['id', 'detail', 'status']);
            foreach ($rows as $r) {
                if (str_contains((string) $r->detail, self::MARK)) continue;
                $update = ['updated_at' => now(), 'detail' => trim(($r->detail ?? '') . "\n" . self::MARK . ' ' . $note)];
                if ($status === 'done' && $r->status !== 'done') { $update['status'] = 'done'; $update['done_at'] = now(); }
                if ($status === 'doing' && $r->status === 'todo') { $update['status'] = 'doing'; }
                DB::table('admin_todos')->where('id', $r->id)->update($update);
            }
        }
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
