<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 주소 통일(www → 대표 주소)과 문의/발송 메일 점검 결과를 할 일 목록에 반영한다.
//  - 메모 날짜를 사장님(애틀랜타) 기준으로 맞춘다: 앞서 붙인 "[10/8 확인]" → "[10/7 확인]"
//  - 이미 사람이 완료한 항목은 되돌리지 않고, 같은 메모가 이미 붙은 항목은 건드리지 않는다.
return new class extends Migration
{
    private const MARK = '[10/7 재확인]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;

        // 1) 메모 날짜 정정 (UTC 날짜가 아니라 애틀랜타 날짜)
        DB::table('admin_todos')
            ->where('detail', 'like', '%[10/8 확인]%')
            ->update(['detail' => DB::raw("REPLACE(detail, '[10/8 확인]', '[10/7 확인]')")]);

        // 2) [제목, 새 상태, 메모]
        $updates = [
            ['주소 통일: www / http / 옛 도메인(somekorean.com)', 'done',
                'www.awesomekorean.com → https://awesomekorean.com 301 이동 배포·확인 완료(경로·쿼리 유지, http→https, 옛 도메인 somekorean.com 도 대표 주소로 이동). 정적 파일(ads.txt 등)은 nginx 가 직접 응답해 www 에서도 열리지만 문제 없음.'],
            ['문의(Contact) 페이지 만들기', 'done',
                '실제 수신 확인 완료: 외부 계정에서 admin@awesomekorean.com 으로 보낸 메일이 ImprovMX → romance76@gmail.com 에 도착. (ImprovMX 별칭 admin/billing/info 추가함, 도메인 MX·SPF 모두 정상)'],
            ['안 쓰는 구글 클라우드 정리', 'doing',
                'awesomekorean-analytics 프로젝트 삭제함(30일 안 복구 가능). 남은 것: awesomekorean-maps(옛 My First Project) 안의 안 쓰는 서비스 계정 analytics-reader 정리, (선택) 지도 키 API 제한을 Places API 하나로 축소. 방문 분석이 계속 "연결됨"인지 확인.'],
            ['가입 인증 메일이 실제로 도착하는지 확인', 'doing',
                '발송 경로는 확인됨: 서버가 보낸 테스트 메일(보내는 주소 AwesomeKorean <noreply@awesomekorean.com>, 리센드)이 지메일 받은편지함에 도착. 남은 것: 새 이메일로 실제 가입해서 인증 메일이 오는지(스팸함 포함) 확인.'],
        ];

        foreach ($updates as [$title, $status, $note]) {
            $row = DB::table('admin_todos')->where('title', $title)->first();
            if (!$row) continue;
            if (str_contains((string) $row->detail, self::MARK)) continue;
            if ($row->status === 'done' && $status !== 'done') continue;   // 사람이 이미 완료한 것은 되돌리지 않음

            $data = [
                'detail' => rtrim((string) $row->detail) . "\n" . self::MARK . ' ' . $note,
                'status' => $status,
                'updated_at' => now(),
            ];
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
