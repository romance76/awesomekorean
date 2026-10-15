<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 2026-10-14 점검 4차: 포인트 악용 방어(게임·안심서비스 체크인·환불)와 경품 월별 장부 시간대 수정을 할 일 목록에 반영한다.
// 제목이 같은(LIKE) 항목만 대상, 메모에 표시가 있으면 건너뜀(여러 번 돌려도 안전), 데이터 삭제 없음.
return new class extends Migration
{
    private const MARK = '[10/14 점검4]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $now = now();

        // [제목 LIKE, 완료 여부, 메모]
        $items = [
            ['게임 포인트 지급 한도·검증 추가%', true, '적용: 한 사람이 하루(뉴욕 시간 자정 기준)에 게임으로 받을 수 있는 포인트를 서버에서 제한(기본 50P, 포인트 설정의 game_daily_cap 으로 변경, 0 이면 게임 포인트 없음) + 점수·결과 요청 분당 60회 제한. 기록(리더보드)은 그대로 저장하고 한도를 넘으면 포인트만 안 줌. 같은 요청을 1000번 보내도 50P 에서 멈추는 것을 시험으로 확인. 점수·시간의 현실값 검증은 게임이 메뉴에서 빠져 있어 하지 않음(게임을 다시 열 때 함께).'],
            ['안심서비스 체크인 포인트 쿨다운·일일 한도 추가%', true, '적용: 체크인 포인트는 하루(뉴욕 시간) 한 번만 지급(체크인 기록은 안전 확인용이라 매번 남김) + 요청 분당 30회 제한. 남은 것: 피보호자 등록 여부 확인은 안심서비스를 다시 열 때 함께.'],
            ['월별 경품 장부의 시간대를%', false, '수정 완료(시간대): 월별 묶기를 고정 오프셋(-04:00, 겨울에 한 시간 어긋남) 대신 PHP 의 America/New_York 시간대로 계산하도록 바꿈. 여름·겨울·서머타임 변경일 경계 시험 통과. 남은 것(상품 코드 암호화): 같은 코드가 당첨자에게 보내는 쪽지 본문에도 평문으로 저장돼 지급 표 한 칸만 암호화하면 효과가 작음 — 쪽지 보관 방식까지 함께 정해야 함.'],
            ['등급 악용 점검%', false, '수정 완료: 환불(…refund) 포인트는 등급 기준(lifetime_points)에 더하지 않도록 User::addPoints 를 고침 — Q&A 현상금을 걸고 지우기 반복, 공동구매·광고 환불로 등급이 오르는 것을 막음(이미 쌓인 값은 그대로). 남은 것: 다계정·좋아요 쌍 한도, 9/12 백필에 게임머니 환전 로그가 섞였는지 운영 DB 점검, 부정 적발 시 등급을 낮추는 관리자 도구.'],
            ['구매·환불·환전 포인트를 회원 등급에 반영할지 결정%', false, '환불은 이미 등급에서 제외하도록 코드를 고침(10/14). 남은 결정: 구매·환전·관리자 조정 포인트를 등급에 반영할지.'],
        ];
        foreach ($items as [$like, $done, $note]) {
            foreach (DB::table('admin_todos')->where('title', 'like', $like)->get(['id', 'detail', 'status']) as $r) {
                if (str_contains((string) $r->detail, self::MARK)) continue;
                $u = ['updated_at' => $now, 'detail' => trim(($r->detail ?? '') . "\n" . self::MARK . ' ' . $note)];
                if ($done && $r->status !== 'done') { $u['status'] = 'done'; $u['done_at'] = $now; }
                DB::table('admin_todos')->where('id', $r->id)->update($u);
            }
        }
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
