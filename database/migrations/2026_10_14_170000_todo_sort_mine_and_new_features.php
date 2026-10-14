<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 2026-10-14: (1) 이번에 만든 기능(2단계 인증·DB 백업·CSP 보고 모드)을 할 일 목록에 반영하고,
// (2) 사람(Kay)이 직접 해야 하는 항목을 분류 '내가 할 것', 결정이 필요한 항목을 '결정 필요'로 옮겨 따로 모아 보게 하고,
// (3) 새 기능 때문에 생긴 "직접 해야 할 일"을 새 항목으로 추가한다.
// 제목이 같은(LIKE) 항목만 대상, 이미 완료된 항목은 건드리지 않음, 메모에 표시가 있으면 건너뜀(여러 번 돌려도 안전), 삭제 없음.
return new class extends Migration
{
    private const MARK = '[10/14 정리]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $now = now();

        // 1) 이번에 만든 기능 반영: [제목 LIKE, 새 상태(done/doing), 메모]
        $features = [
            ['관리자 2단계 인증(2FA) 추가%', 'done', '구현 완료: 인증 앱(구글 OTP·Authy 등) 6자리 코드 방식. 스스로 켠 계정만 로그인 때 코드를 요구하고(안 켠 계정은 그대로), 복구 코드 8개(1회용)·코드 재사용 방지·시도 횟수 제한·소셜 로그인 우회 차단 포함. 설정: 관리자 › 시스템 › 2단계 인증. 비상 해제: 최고관리자 화면 API 또는 서버에서 php artisan user:2fa-reset 이메일. 표준(RFC 6238/4226) 시험값 35개와 판정 로직 24개를 통과. ※ 실제 폰으로 켜서 로그인해 보는 확인은 사람이 해야 함(새 항목 참고).'],
            ['DB 자동 백업 + 복구 연습%', 'doing', '만든 것: 매일 03:50(뉴욕) php artisan db:backup(gzip, 7일 보관, 끝까지 만들어졌는지 확인 후 저장, 디스크 1GB 미만이면 건너뜀) + php artisan db:restore-check(임시 DB 에 되살려 표 개수 비교 후 삭제). 설명서: qa/DB_BACKUP.md. 남은 것(사람): 서버 밖 보관 장소를 정하고 .env 에 BACKUP_UPLOAD_CMD 설정, 첫 복구 연습 실행.'],
            ['전체 CSP(스크립트 실행 제한) 켜기%', 'doing', '1단계 완료: 아무것도 막지 않는 보고 전용 정책(Content-Security-Policy-Report-Only)을 HTML 화면에 붙였고, 위반은 /api/csp-report 로 모여 종류별 하루 1건씩 storage/logs/csp-report.log 에 기록됨(주소 쿼리·개인정보는 버림). 다음: 며칠 쌓인 로그로 빠진 출처를 정리한 뒤 강제 모드로 올리기.'],
        ];
        foreach ($features as [$like, $status, $note]) {
            foreach (DB::table('admin_todos')->where('title', 'like', $like)->get(['id', 'detail', 'status']) as $r) {
                if (str_contains((string) $r->detail, self::MARK)) continue;
                $u = ['updated_at' => $now, 'detail' => trim(($r->detail ?? '') . "\n" . self::MARK . ' ' . $note)];
                if ($status === 'done' && $r->status !== 'done') { $u['status'] = 'done'; $u['done_at'] = $now; }
                if ($status === 'doing' && $r->status === 'todo') $u['status'] = 'doing';
                DB::table('admin_todos')->where('id', $r->id)->update($u);
            }
        }

        // 2) 분류 이동 (이미 완료된 항목은 그대로)
        $mine = [   // 사람이 직접 해야 함: 구글·DNS·서버·실제 폰 등 계정/기기 접근이 필요한 것
            '구글에 색인된 페이지 수 확인%', 'Google AdSense 가입 + 코드 연동%', '가입 인증 메일이 실제로 도착하는지 확인%', '안 쓰는 구글 클라우드 정리%',
            '서버 재부팅%', '애널리틱스 "향상된 측정" 켜기%', '애드센스 거절 사유 확인%', '오픈 전 서버 작업 순서%', 'MySQL 느린 쿼리 기록%',
            'Kay 휴대폰 실기기 확인%', 'SPF/DKIM 에 실제 발신 서비스%', '푸시 알림(Firebase) 테스트 결과 확인%', '내일 방문 분석에 본 사이트 방문이 나오는지 확인%',
            '실제 아이폰에서 홈 마퀴 끊김 확인%', 'API 키 변경 메일 수신 확인%', '관리자 보안 화면 동작 확인%', '새 관리자 화면 휴대폰 점검 루틴%',
            '애널리틱스 계정/속성/스트림 이름을%', '옛 info.awesomekorean.com 을 새 주소로 301 이동%', 'fail2ban 에 nginx 404/403%',
            '음악 관리자 표시150/숨김84%', '반복 일정·가입 보너스 실제 동작 시험%', '구글 지도 키를 사이트 주소로만 제한%',
        ];
        $decide = [   // 결정·승인이 먼저 필요함: 방침, 삭제 승인, 정책
            'AI 생성 글 검수 방침%', '네이버 지식인에서 가져온 Q&A%', '재신청 시점 정하기%', '테스트 운영자·더미 계정 40개 정리%', '임시 중고장터/부동산 매물 정리%',
            '정보 글 가짜 조회수%', '구매·환불·환전 포인트를 회원 등급에 반영할지 결정%', '친구 카드·채팅 참가자·쪽지함의 시·주 표시%', '오타 계정 id 2902%',
            '신규 가입 시 관리자 알림 메일 필요 여부 결정%', '어썸 뮤직 PWA%', '쿠키 동의 배너 필요성 검토%', '뉴스(RSS·번역) 게재 범위 점검%',
            '더미 사용자·더미 동호회 정리%', '환불 정책 정리%', '정보 글 품질 점검%', '오래된 pending 결제 자동 정리%',
        ];
        foreach ([['내가 할 것', '직접 해야 해요(계정·기기 접근이 필요)', $mine], ['결정 필요', '결정·승인이 먼저 필요해요', $decide]] as [$cat, $why, $patterns]) {
            foreach ($patterns as $like) {
                foreach (DB::table('admin_todos')->where('title', 'like', $like)->where('status', '!=', 'done')->get(['id', 'detail', 'category']) as $r) {
                    if (str_contains((string) $r->detail, self::MARK . ' 분류')) continue;
                    DB::table('admin_todos')->where('id', $r->id)->update([
                        'category' => $cat, 'updated_at' => $now,
                        'detail' => trim(($r->detail ?? '') . "\n" . self::MARK . ' 분류를 \'' . $cat . '\'(으)로 옮김 — ' . $why . '. 원래 분류: ' . $r->category),
                    ]);
                }
            }
        }

        // 3) 새 기능 때문에 생긴 "직접 해야 할 일"
        $sort = (int) DB::table('admin_todos')->max('sort_order');
        $new = [
            ['내가 할 것', 'high', '내 관리자 계정에 2단계 인증 켜기 + 로그인 시험',
                "관리자 › 시스템 › 2단계 인증 에서 '켜기 시작' → 폰의 인증 앱에 등록 → 6자리 코드 입력 → 복구 코드 8개를 폰이 아닌 곳(종이·비밀번호 관리자)에 저장.\n확인: 로그아웃했다가 이메일·비밀번호로 로그인하면 인증 코드를 묻고, 코드를 넣으면 들어와짐. 복구 코드 하나로도 들어와지는지 한 번 시험(쓴 코드는 못 씀).\n주의: 켜면 그 계정은 구글·아마존 소셜 로그인을 못 씀. 문제가 생기면 서버에서 php artisan user:2fa-reset 이메일 로 풀 수 있음."],
            ['내가 할 것', 'high', 'DB 백업을 서버 밖에도 보관하기 (BACKUP_UPLOAD_CMD)',
                "백업은 매일 자동으로 서버 안(storage/app/backups)에 쌓이지만, 서버가 망가지면 같이 사라짐. 서버 밖 저장소(구글 드라이브·Backblaze B2·S3 등)를 정해 rclone 을 설치·연결하고, 서버 .env 에 BACKUP_UPLOAD_CMD=\"rclone copy {file} remote:폴더/\" 를 추가.\n자세한 방법: qa/DB_BACKUP.md. 어떤 저장소를 쓸지 정해 주시면 설정 명령을 만들어 드릴 수 있음."],
            ['내가 할 것', 'medium', '첫 DB 복구 연습 실행 (php artisan db:restore-check)',
                "백업이 하루 이상 쌓인 뒤(이용자가 적은 새벽) 서버에서 sudo -u www-data php8.2 artisan db:restore-check 를 한 번 실행. 최근 백업을 임시 DB 에 되살려 표 개수를 운영 DB 와 비교하고 임시 DB 를 지움(운영 DB 는 읽기만 함). 권한이 없다고 나오면 qa/DB_BACKUP.md 의 '수동 복구'로 연습.\n한 달에 한 번 반복 권장."],
            ['보안', 'medium', 'CSP 보고 로그 확인 후 강제 모드로 전환',
                "며칠~1주일 뒤 storage/logs/csp-report.log 를 보고(Claude 에게 보여 달라고 하면 정리해 줌), 정책에 빠진 출처를 추가한 뒤 Content-Security-Policy-Report-Only 를 강제 모드(Content-Security-Policy)로 올리기. 'unsafe-inline' 제거는 그 다음 단계."],
            ['결정 필요', 'medium', '광고 할인이 실제 결제 금액에 적용되는지 확인',
                "코드상 서버가 할인 금액을 계산하는 곳은 패키지 결제뿐이고, 광고 할인(applies_to_ads)은 화면 표시용 API 에서만 보임. 광고 결제 화면에서 이벤트 할인 중 실제 차감 금액을 확인하고, 적용이 안 되면 (a) 서버 계산에 적용 (b) 광고 할인 표시를 숨김 중 선택."],
        ];
        foreach ($new as [$cat, $pri, $title, $detail]) {
            if (DB::table('admin_todos')->where('title', $title)->exists()) continue;
            $sort += 10;
            DB::table('admin_todos')->insert([
                'title' => $title, 'detail' => $detail, 'category' => $cat, 'priority' => $pri, 'status' => 'todo',
                'sort_order' => $sort, 'done_at' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
