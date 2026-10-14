<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 2026-10-14 코드 점검: 할 일 목록에 남아 있던 항목 중 이미 코드에 들어 있거나 이번에 고친 것을 반영한다.
// - 코드에서 직접 확인한 항목만 '완료'로 바꾸고, 일부만 끝난 항목은 상태는 그대로 두고 메모만 남긴다.
// - 제목이 같은(LIKE) 항목만 대상, 메모에 표시가 있으면 건너뜀(여러 번 돌려도 안전), 데이터 삭제 없음.
return new class extends Migration
{
    private const MARK = '[10/14 점검]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;

        // [제목 LIKE, 완료 여부, 메모]
        $items = [
            ['deploy.sh 에 queue:restart%', true, 'deploy.sh 에 이미 들어 있음 — 배포 후 queue:restart(Step 6), 마이그레이션은 빌드 전에 먼저 실행(Step 2b).'],
            ['배포 중복 실행 방지%', true, '배포 워크플로 concurrency 그룹(순차 실행) + deploy.sh flock(2차 잠금) + GitHub 러너에서 빌드(서버 부하 없음)가 이미 적용돼 있음. "푸시 묶기"는 운영 규칙이라 코드 대상이 아님.'],
            ['사이트 설정 껍데기 토글 점검%', true, '점검 모드·가입 허용·이메일 인증·비밀번호 최소 길이는 SiteFlags 로 실제 적용됨(CheckMaintenance 미들웨어, AuthController 가입·로그인, EnsureEmailVerified, 채팅 입장).'],
            ['관리자 수집 버튼을 큐로%', true, 'AdminCollectService 가 종류별 잠금(연타 방지) 후 RunAdminCollect 큐 작업으로 넘기고 바로 응답하도록 이미 구현됨.'],
            ['인보이스 1단계%', true, 'invoices·invoice_deliveries 표, 결제 완료 시 큐 메일(SendInvoiceEmail), 관리자 재발송(최고관리자), 고객 영수증 경로가 구현돼 있음.'],
            ['진짜 VAPID 키 생성%', true, 'AdminSettingsController::generateVapid 를 OpenSSL P-256 실제 키 생성(base64url 65/32바이트)으로 교체. 이미 푸시 키가 있으면 덮어쓰지 않음(기존 구독 보호).'],
            ['.claude/settings.local.json 의 임시 허용 규칙%', true, '현재 파일에 DELETE 규칙은 없음. 참고: 같은 파일에 서버 IP·root 접속 허용 규칙이 남아 있어 공개 저장소에 노출되므로 별도 정리를 권장.'],
            ['스케줄러 매분 작업 정리%', false, '경품 매분 작업은 sweepstakes:tick 하나로 통합돼 있음. reservations:expire·promotions:expire 에 withoutOverlapping 추가(10/14). 옛 빌드 파일은 deploy.sh 가 14일 보존(의도적). 남은 것: 서버 nginx sites-enabled 의 bak 사본 제거(서버 작업).'],
            ['CI 보강%', false, 'CI 는 이미 app·routes·config·database·bootstrap PHP 문법 검사 + vite 빌드 확인까지 함(php-syntax.yml). 남은 것: 새 기능 킬 스위치 점검, 배포 후 운영 화면 왕복 체크리스트.'],
        ];

        foreach ($items as [$like, $done, $note]) {
            $rows = DB::table('admin_todos')->where('title', 'like', $like)->get(['id', 'detail', 'status']);
            foreach ($rows as $r) {
                if (str_contains((string) $r->detail, self::MARK)) continue;
                $update = ['updated_at' => now(), 'detail' => trim(($r->detail ?? '') . "\n" . self::MARK . ' ' . $note)];
                if ($done && $r->status !== 'done') { $update['status'] = 'done'; $update['done_at'] = now(); }
                DB::table('admin_todos')->where('id', $r->id)->update($update);
            }
        }
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
