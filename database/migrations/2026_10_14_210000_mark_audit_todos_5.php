<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 2026-10-14 점검 6차(마무리): 이미 구현된 항목 완료 표시, 시기가 정해진 항목에 날짜 메모, 서버 접속이 필요한 일을 '내가 할 것'으로 이동.
// 제목이 같은(LIKE) 항목만 대상, 메모에 표시가 있으면 건너뜀(여러 번 돌려도 안전), 데이터 삭제 없음.
return new class extends Migration
{
    private const MARK = '[10/14 점검6]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $now = now();

        // [제목 LIKE, 완료 여부, 새 분류(또는 null), 메모]
        $items = [
            ['회원 일괄 작업·검색 확장·IP 차단 기간%', true, null, '코드 확인 결과 모두 구현돼 있음: 여러 명 일괄 정지·정지 해제·등급 변경(최고관리자만, 운영진 계정·본인은 건너뜀, 감사 로그 기록, 한 번에 50명), 이름·이메일·별명·전화·번호(ID) 검색, IP 차단 기간(자동 해제)과 대역(CIDR) 차단. 참고: 회원을 IP 로 검색하는 기능은 회원 IP 를 저장하지 않아 없음 — 개인정보라서 필요하면 로그인 IP 기록 도입 여부를 따로 결정.'],
            ['HSTS 기간 1년으로 늘리기%', false, null, '시기 메모: 현재 30일 설정은 10/7 에 처음 적용됐고, 조건은 "한 달 이상 문제 없이 운영"이므로 11/7 이후에 가능. 그때 인증서 갱신·접속 문제가 없었는지 확인하고 app/Http/Middleware/SecurityHeaders.php 의 max-age=2592000 을 31536000 으로 바꾸면 됨(되돌리기 어려우니 서브도메인·인증서 상태를 먼저 확인).'],
            ['스케줄러 매분 작업 정리%', false, '내가 할 것', '남은 일은 서버 접속이 필요한 것: nginx sites-enabled 폴더의 bak 사본 제거(서버 파일 정리). 코드로 할 수 있는 부분(겹침 방지·경품 매분 작업 통합·빌드 보존 기간)은 끝남.'],
        ];
        foreach ($items as [$like, $done, $cat, $note]) {
            foreach (DB::table('admin_todos')->where('title', 'like', $like)->where('status', '!=', 'done')->get(['id', 'detail', 'category']) as $r) {
                if (str_contains((string) $r->detail, self::MARK)) continue;
                $u = ['updated_at' => $now, 'detail' => trim(($r->detail ?? '') . "\n" . self::MARK . ' ' . $note . ($cat ? ' (원래 분류: ' . $r->category . ')' : ''))];
                if ($done) { $u['status'] = 'done'; $u['done_at'] = $now; }
                if ($cat) $u['category'] = $cat;
                DB::table('admin_todos')->where('id', $r->id)->update($u);
            }
        }
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
