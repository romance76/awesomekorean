<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 10/8 에 실제 사이트/구글 화면으로 확인된 항목만 할 일 목록에 반영한다.
//  - 확인된 것은 완료 처리하고 메모를 붙인다.
//  - 진행 중인 것은 '진행 중'으로 바꾸고 현재 상태를 메모로 남긴다.
//  - 관리자가 같은 이름의 항목을 지웠거나 이미 완료해 둔 경우, 이미 메모가 붙은 경우는 건드리지 않는다.
return new class extends Migration
{
    private const MARK = '[10/8 확인]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;

        // [제목, 새 상태, 메모, (선택) 설명 전체 교체]
        $updates = [
            ['구글 지도 키를 사이트 주소로만 쓰도록 제한', 'done',
                '지도 키는 서버에서만 호출하므로 "웹사이트" 제한이 아니라 서버 IP(68.183.60.70) 제한이 맞음. 구글 콘솔에서 IP 제한이 걸려 있고(끝 4자리 BsAE 확인) 허용 API 목록에 Places API 포함. 구글 결제(무료 체험 종료)가 꺼져 있어 지도 기능 자체는 업그레이드 전까지 멈춰 있음.',
                '서버 전용 키라서 "웹사이트(리퍼러)" 제한이 아니라 서버 IP 제한이 맞음. 구글 클라우드 awesomekorean-maps 프로젝트의 Maps Platform API Key 에 IP 68.183.60.70 제한이 이미 걸려 있음. (선택) API 제한을 Places API 하나로 더 줄일 수 있음.'],
            ['구글 Search Console 에 사이트 등록 + 사이트맵 제출 확인', 'done',
                'awesomekorean.com 등록됨. /sitemap-main.xml 상태 Success, 발견된 페이지 173개. (/sitemap.xml 은 구글 쪽에서 "Couldn\'t fetch" 로 남아 있으나 같은 내용이라 무시)'],
            ['소개(About) 페이지 만들기', 'done',
                '/about 새 디자인 페이지 배포됨. 푸터 "안내" 칸과 정보 페이지 하단 푸터에 링크 있음.'],
            ['문의(Contact) 페이지 만들기', 'done',
                '/contact 배포됨, 푸터 링크 있음. 단 admin@awesomekorean.com 으로 실제 메일이 도착하는지는 직접 확인 필요.'],
            ['개인정보처리방침에 광고·쿠키 문구 추가', 'done',
                '방침 11항에 Google AdSense/쿠키 설명과 adssettings.google.com, aboutads.info 링크가 있음.'],
            ['이용약관·면책 문구 확인 (법률/세금/이민 정보)', 'done',
                '정보 목록과 모든 정보 글 하단 푸터에 "전문가 상담을 대체하지 않음" 고지가 있고, 이용약관 제30조 면책조항도 있음.'],
            ['ads.txt 준비', 'done',
                'https://awesomekorean.com/ads.txt 에 google.com, pub-5961739312236096, DIRECT 줄이 있고 애드센스 Sites 화면에서 Ads.txt status 가 Authorized.'],
            ['애드센스 가입 신청', 'done',
                '애드센스 Sites 에서 awesomekorean.com 승인 상태가 "Getting ready"(준비/검토 중)로 표시됨. 사이트에 애드센스 코드(게시자 ID pub-5961739312236096)도 연결·배포됨.'],
            ['구글에 색인된 페이지 수 확인', 'doing',
                '사이트맵 173개를 구글이 읽었음. 홈(/)은 "URL is on Google" 로 색인됨. 나머지는 구글이 읽어 가는 중이라 2~3일 뒤 Indexing > Pages 를 다시 확인.'],
            ['Google AdSense 가입 + 코드 연동', 'doing',
                '가입 완료, 코드 연동 완료(홈·정보 목록·정보 글 헤드에 확인). 남은 것: 구글 승인 심사(Getting ready, 10/1 시작 — 보통 며칠~4주). 승인 메일은 romance76@gmail.com 으로 옴.'],
            ['안 쓰는 구글 클라우드 정리', 'doing',
                '제한 없던 옛 API 키(…VZqs) 삭제함, 프로젝트 이름을 awesomekorean / awesomekorean-maps 로 정리 진행. 확인 결과 지도 키는 awesomekorean-maps(옛 My First Project), 유튜브 키와 방문 분석 서비스 계정은 awesomekorean(옛 SPY-Scalper-DB). 남은 것: 빈 프로젝트 awesomekorean-analytics 종료, awesomekorean-maps 의 안 쓰는 analytics-reader 정리.'],
            ['주소 통일: www / http / 옛 도메인(somekorean.com)', 'todo',
                'http → https 와 somekorean.com → awesomekorean.com 은 잘 넘어감. 그러나 www.awesomekorean.com 이 awesomekorean.com 으로 넘어가지 않고 그대로 열려 같은 사이트가 두 주소로 보임. 서버(nginx)에서 www → 주소 통일 설정이 필요.'],
        ];

        foreach ($updates as $u) {
            [$title, $status, $note] = $u;
            $replaceDetail = $u[3] ?? null;

            $row = DB::table('admin_todos')->where('title', $title)->first();
            if (!$row) continue;
            if (str_contains((string) $row->detail, self::MARK)) continue;   // 이미 반영됨
            if ($row->status === 'done' && $status !== 'done') continue;      // 사람이 이미 완료한 것은 되돌리지 않음

            $detail = $replaceDetail ?? (string) $row->detail;
            $detail = rtrim($detail) . "\n" . self::MARK . ' ' . $note;

            $data = ['detail' => $detail, 'status' => $status, 'updated_at' => now()];
            if ($status === 'done' && $row->status !== 'done') $data['done_at'] = now();
            if ($status !== 'done') $data['done_at'] = null;

            DB::table('admin_todos')->where('id', $row->id)->update($data);
        }
    }

    public function down(): void
    {
        // 진행 상황 기록이라 되돌리지 않음
    }
};
