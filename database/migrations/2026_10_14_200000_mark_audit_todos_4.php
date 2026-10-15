<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 2026-10-14 점검 5차: 배포 후 확인 자동화(스모크 스크립트)·체크리스트·킬 스위치 목록 문서를 만들어 "CI 보강" 항목을 마무리한다.
// 제목이 같은(LIKE) 항목만 대상, 메모에 표시가 있으면 건너뜀(여러 번 돌려도 안전), 데이터 삭제 없음.
return new class extends Migration
{
    private const MARK = '[10/14 점검5]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $now = now();
        $note = '완료: (1) CI 는 이미 PHP 문법 검사 + vite 빌드 확인을 함, (2) 배포 후 자동 확인 스크립트 qa/scripts/post_deploy_smoke.sh 추가(화면·API·로그인·보안 헤더·속도를 읽기 전용으로 확인, 라이브에서 전부 통과 확인), '
            . '(3) 배포 후 사람이 보는 체크리스트와 킬 스위치 전체 목록·끄는 법을 qa/POST_DEPLOY_CHECKLIST.md 에 정리. 기존 비상 스위치는 가입 보너스·경품 반복 일정·자동 수집·SNS 자동 게시 4개이며 이번에 만든 기능(2단계 인증·DB 백업·CSP 보고 모드)은 문서에 끄는 법을 적음.';
        foreach (DB::table('admin_todos')->where('title', 'like', 'CI 보강%')->get(['id', 'detail', 'status']) as $r) {
            if (str_contains((string) $r->detail, self::MARK)) continue;
            $u = ['updated_at' => $now, 'detail' => trim(($r->detail ?? '') . "\n" . self::MARK . ' ' . $note)];
            if ($r->status !== 'done') { $u['status'] = 'done'; $u['done_at'] = $now; }
            DB::table('admin_todos')->where('id', $r->id)->update($u);
        }
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
