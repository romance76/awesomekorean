<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 관리자 페이지 "할 일 목록" — 앞으로 만들거나 보강할 것들을 한곳에 적어 두고 직접 고치는 용도
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_todos')) {
            Schema::create('admin_todos', function (Blueprint $t) {
                $t->id();
                $t->string('title', 200);
                $t->text('detail')->nullable();
                $t->string('category', 30)->default('기능');
                $t->string('priority', 10)->default('medium'); // high / medium / low
                $t->string('status', 10)->default('todo');     // todo / doing / done
                $t->unsignedInteger('sort_order')->default(0);
                $t->timestamp('done_at')->nullable();
                $t->timestamps();
                $t->index(['status', 'sort_order']);
            });
        }

        if (DB::table('admin_todos')->count() > 0) return;

        $now = now();
        $rows = [
            // [분류, 우선순위, 상태, 제목, 설명]
            ['보안', 'high', 'todo', '관리자 2단계 인증(2FA) 추가', '관리자 로그인 때 비밀번호 외에 휴대폰 인증 앱 코드를 한 번 더 확인. 계정이 털려도 관리자 페이지는 못 열게 하는 가장 효과 큰 방어.'],
            ['보안', 'high', 'todo', '구글 지도 키를 사이트 주소로만 쓰도록 제한', '구글 클라우드 콘솔 → API 및 서비스 → 사용자 인증 정보 → 해당 키 → 애플리케이션 제한사항 "웹사이트"에 https://awesomekorean.com/* 와 https://www.awesomekorean.com/* 추가, API 제한사항은 사용하는 API만 선택. (직접 해야 하는 작업)'],
            ['서버', 'high', 'todo', '서버 재부팅 (보안 업데이트 7건 대기)', '재부팅해야 적용되는 커널/보안 업데이트가 쌓여 있음. 몇 분간 사이트가 끊기므로 이용자가 적은 시간(미국 동부 새벽)에 진행.'],
            ['서버', 'high', 'todo', 'DB 자동 백업 + 복구 연습', '하루 한 번 DB를 백업해 서버 밖(다른 저장소)에 보관하고, 실제로 복구되는지 한 번 연습해 두기.'],
            ['보안', 'medium', 'todo', '비밀번호를 바꾸면 기존 로그인 전부 끊기', '지금은 비밀번호를 바꿔도 이미 발급된 로그인 토큰이 만료될 때까지 유지됨. 바꾸는 즉시 다른 기기 로그인도 끊기게.'],
            ['보안', 'medium', 'todo', '로그인 토큰을 보안 쿠키로 이전', '지금은 브라우저 저장소에 토큰이 있어 화면 코드(XSS)가 뚫리면 위험. HttpOnly 쿠키로 옮기면 훨씬 안전.'],
            ['보안', 'medium', 'todo', '전체 CSP(스크립트 실행 제한) 켜기', '허용한 출처의 스크립트만 실행되게 하는 헤더. 결제/지도/광고 스크립트 출처를 정리한 뒤 "보고만 하는 모드"로 먼저 테스트하고 적용.'],
            ['보안', 'medium', 'todo', '운영진(moderator) 권한 범위 점검', 'moderator 도 admin 미들웨어를 통과함. 컨트롤러마다 어디까지 허용할지 확인.'],
            ['보안', 'low', 'todo', '임시 점검 주소 정리 (mail-debug 등)', '메일 문제 진단용으로 만든 임시 주소와 설정 화면 버튼 정리.'],
            ['보안', 'low', 'todo', 'firebase 라이브러리 보안 권고 4건', 'npm audit 에 남은 항목. firebase 새 버전이 나오면 올려서 해결.'],
            ['보안', 'low', 'todo', 'HSTS 기간 1년으로 늘리기', '지금은 30일. 한 달 정도 문제 없이 돌아간 뒤 1년으로.'],
            ['기능', 'medium', 'todo', '동호회: 모임 캘린더 · 자료실 · 등급별 열람 권한', '동호회 안내 페이지에서 약속한 운영 기능을 실제로 구현.'],
            ['기능', 'medium', 'todo', '푸시 알림(Firebase) 테스트 결과 확인', '관리자 설정의 테스트 알림이 폰에 오는지 확인하고, 앱을 닫았을 때 오는 전화 알림 개선.'],
            ['기능', 'low', 'todo', '음성 통화 버튼 다시 켜기 (앱 출시 때)', 'resources/js/config/features.js 의 VOICE_CALL_ENABLED 를 true 로.'],
            ['데이터', 'medium', 'todo', '임시 중고장터/부동산 매물 정리', '회원 등록이 시작되면 source=scraped 매물을 지우고 자동 수집 스케줄(market:scrape, realestate:scrape)을 끄기.'],
            ['데이터', 'medium', 'todo', 'info.awesomekorean.com 글 130+개 이전', '기존 정보 글을 어썸코리안 /info 로 옮기기.'],
            ['콘텐츠', 'medium', 'doing', 'Google AdSense 가입 + 코드 연동', '가입 승인 후 광고 코드 연동.'],
            ['서버', 'medium', 'done', '서버 스케줄러(cron) 매분 실행', '자동 수집·알림 작업이 제때 돌도록 서버에 매분 실행 등록.'],
            ['보안', 'high', 'done', '관리자 자동 로그아웃 후에도 화면이 남던 문제', '만료/서버 거부 시 즉시 로그아웃, 관리자 권한 주기적 재확인, 30분 무조작 자동 로그아웃.'],
            ['보안', 'high', 'done', '외부 서비스 키 DB 암호화 + 변경 시 메일 알림', 'API 키를 암호화해 저장하고, 바꿀 때마다 관리자 메일로 전체 현황 발송.'],
            ['보안', 'high', 'done', '로그인 시도 제한 · 업로드 · 채팅 채널 · XSS 보안 점검 1차', '공개 전 보안 점검 1차 수정 완료.'],
        ];
        foreach ($rows as $i => [$cat, $pri, $st, $title, $detail]) {
            DB::table('admin_todos')->insert([
                'title' => $title, 'detail' => $detail, 'category' => $cat, 'priority' => $pri, 'status' => $st,
                'sort_order' => ($i + 1) * 10, 'done_at' => $st === 'done' ? $now : null, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_todos');
    }
};
