<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 2026-10-11 2차 작업(감사 로그 화면·즐겨찾기·CI·권한 세부·한국어 메시지·HTML 저장 방지·환불 사유·일괄 작업 등)을 마친 뒤
// 할 일 목록에 반영한다. 끝난 것은 done, 일부만 끝난 것은 doing(진행 중)으로 두고 남은 부분을 메모에 적는다.
// 여러 번 돌려도 안전(메모에 표시가 있으면 건너뜀), 데이터 삭제 없음.
return new class extends Migration
{
    private const MARK = '[10/11 2차]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;

        // [제목 like, 상태(done|doing|keep), 메모]  — keep 은 상태는 그대로 두고 메모만 덧붙임
        $items = [
            ['감사 로그 보는 화면%', 'done', '시스템 > 감사 로그(최고관리자, 휴대폰 포함): 누가·언제·무엇을·IP·성공/실패, 검색·기간·대상·실패만. 환불은 사유까지 기록. (변경 전/후 값 기록 확대는 아직 — 지금은 보낸 칸 이름만)'],
            ['관리자 즐겨찾기를 서버에 저장%', 'done', '메뉴 허브 각 타일의 ☆ 를 누르면 홈 "즐겨찾기"에 모임. 계정별 서버 저장(admin_favorites)이라 PC·휴대폰 같은 구성, 서버가 안 되면 브라우저 저장으로 대체. 운영자도 사용 가능.'],
            ['CLAUDE.md·메모리의 서버 경로 정정%', 'done', 'CLAUDE.md 의 서버 경로를 /var/www/awesomekorean 으로 정정(배포 성공 확인 방법도 추가).'],
            ['입력 오류 메시지를 한국어로%', 'done', 'API 요청은 한국어 검증 메시지(lang/ko/validation.php, SetApiLocale), 없는 항목 404 는 한국어 문구로 내부 클래스 이름 숨김. 컨트롤러가 직접 쓴 메시지는 그대로 우선.'],
            ['번들 크기 줄이기%', 'done', '확인 결과 three.js(672KB)·leaflet(146KB)은 이미 필요할 때만 불러오는 별도 파일이라 첫 화면에 영향 없음. 첫 화면 app 파일은 약 555KB(압축 190KB) — 더 줄이려면 라우트별 분리 점검이 필요하지만 급하지 않음.'],
            ['CI 보강%', 'doing', 'CI 에 database/·bootstrap/ PHP 문법 검사와 프런트 vite 빌드 검사를 추가함. 남은 것: 새 기능마다 끄는 스위치 점검, 배포 후 운영 화면 왕복 체크리스트.'],
            ['운영자·관리자 권한 세부 정리%', 'doing', '채팅 영구제명에 운영진·본인 보호 추가, Entry 거래 조회의 회원 이메일은 최고관리자만 보이게 함. 남은 것: 경품 claims 조회가 데이터를 만드는 읽기+쓰기 혼합.'],
            ['글·댓글·쪽지에 HTML 저장 방지%', 'doing', '글 제목·내용, 댓글, 쪽지에서 HTML 태그를 저장하지 않음(PlainText, 부등호·수식 글은 그대로) + 쪽지에 금칙어 필터 추가. 남은 것: 없는 글·부모 댓글에도 댓글이 달리는 문제.'],
            ['환불 정책 정리%', 'doing', '환불 시 사유 입력(휴대폰·PC 모두) + 처리자·사유·금액을 감사 로그에 기록. 환불 로직(잠금·포인트 부족 차단·Stripe 선행)은 그대로. 남은 것: 포인트 부족 시 부분 환불 정책, 직접 결제(전단) 환불 경로 정리.'],
            ['스케줄러 매분 작업 정리%', 'doing', '확인 결과 withoutOverlapping 과 옛 빌드 파일 정리(14일 보존)는 이미 되어 있음. 남은 것: 경품 매분 작업 3개 합치기, 서버 nginx sites-enabled 의 bak 사본 제거(서버 작업).'],
            ['회원 일괄 작업·검색 확장%', 'doing', '회원 목록에서 여러 명(최대 50) 일괄 정지/해제(운영진·본인 제외), 번호·전화·IP 로도 검색. 남은 것: 일괄 등급 변경, IP 차단 기간(자동 해제)·대역(CIDR).'],
            ['메뉴 개편 3~9단계%', 'keep', '[추가] 휴대폰에 같은 그룹 화면으로 옮겨 가는 칩 줄 추가(홍보·이벤트 등 모든 그룹), 회원 패널(PC) 버그 수정·확인창 교체·일괄 작업. 화면 자체를 한 파일로 합치는 것은 하지 않기로 함(딥링크·권한 차이 위험이 이득보다 큼).'],
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
