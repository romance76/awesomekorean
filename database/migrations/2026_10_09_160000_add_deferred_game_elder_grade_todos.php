<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 회원 등급 15단계 개편 중 나온 후속 작업 중, 당분간 하지 않기로 한 것(게임·안심서비스)을 할 일 목록에만 남겨 둔다.
// 제목이 같은 항목이 이미 있으면 건너뛴다(여러 번 돌려도 안전). 기존 항목은 건드리지 않는다.
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;

        $now = now();
        $sort = (int) DB::table('admin_todos')->max('sort_order');

        // [분류, 우선순위, 상태, 제목, 설명]
        $rows = [
            ['게임', 'low', 'todo', '게임 포인트 지급 한도·검증 추가 (보류 — 게임 작업 때 함께)',
                "당분간 게임은 손대지 않기로 함. 게임 작업을 다시 시작할 때 같이 처리.\n"
                . "- POST /api/games/scores (GameScoreController::store): 점수를 클라이언트가 보내고 서버 검증·횟수 제한이 없어 판당 최대 5P 를 반복 적립 가능.\n"
                . "- POST /api/games/result (GameRecordController): 레벨업 3P·신기록 10P 한도 없음, time_ms 를 계속 줄이면 신기록 반복.\n"
                . "해야 할 일: 하루 적립 한도(횟수 또는 P) + throttle + 서버 쪽 점수/시간 현실값 검증.\n"
                . "참고: 회원 등급(링)은 누적 획득 포인트 기준이라, 이 구멍이 열려 있으면 링을 쉽게 올릴 수 있음. 자세한 내용은 qa/member_grades_15_plan.md §6."],

            ['안심서비스', 'low', 'todo', '안심서비스 체크인 포인트 쿨다운·일일 한도 추가 (보류 — 안심서비스 작업 때 함께)',
                "나중에 안심서비스를 손볼 때 같이 처리.\n"
                . "- POST /api/elder/checkin (ElderController::checkin): 쿨다운·일일 한도·피보호자 등록 확인이 없어 호출할 때마다 5~50P 적립 가능.\n"
                . "해야 할 일: 하루 1회(또는 checkin_interval 기준) 제한 + 피보호자(ElderSetting) 등록 여부 확인.\n"
                . "참고: 회원 등급(링)은 누적 획득 포인트 기준. qa/member_grades_15_plan.md §6."],

            ['등급', 'medium', 'todo', '구매·환불·환전 포인트를 회원 등급에 반영할지 결정',
                "현재 User::addPoints() 는 양수 지급이면 모두 lifetime_points(등급 기준)에 더함. 포인트를 사용해도 등급은 내려가지 않음(음수는 lifetime 에 영향 없음).\n"
                . "문제: 결제로 포인트를 구매해도 등급이 올라감(예: 기업이 광고용으로 포인트를 한 번에 구매 → 곧바로 높은 링). 구매한 포인트를 광고에 써도 등급은 그대로 유지됨.\n"
                . "선택지: (a) 지금처럼 모두 반영 (b) 구매·환불·환전·관리자 조정은 등급에서 제외(권장 — FlyerService::refund() 가 이미 쓰는 방식) (c) 구매는 일정 비율만 반영.\n"
                . "결정되면 addPoints() 에 '등급 반영 여부' 옵션을 추가하고 구매(PaymentController)·환불·게임머니 역환전·포커칩 환전·장터 홀드 수입 호출부에 적용. 결제는 현재 보류 중(Stripe → CardPointe 예정)이라 결제 재개 전에 정하면 됨."],

            ['등급', 'medium', 'todo', '등급 악용 점검: Q&A 현상금 환불 반복 · 다계정 · 게임머니 백필',
                "- Q&A 현상금: 걸 때는 lifetime 이 안 줄고, 답변 없이 삭제하면 환불이 addPoints() 로 lifetime 을 올림 → 걸고 지우기 반복 시 등급 상승 (QaController 71~83, 105~116행).\n"
                . "- 다계정: 가입 10P + 프로필 30P, 좋아요 보상은 같은 사람→같은 글쓴이 쌍 한도가 없음.\n"
                . "- 9/12 lifetime 백필(2026_09_12_034715_add_lifetime_points_to_users)이 point_logs 의 양수를 모두 합쳐서, 게임머니 환전 로그(type=game_money_in, 게임머니 수량이 양수로 기록됨)가 섞였을 수 있음 → 해당 회원 점검 쿼리 필요.\n"
                . "- 부정 적발 시 등급을 낮추는 관리자 도구(lifetime 조정 + 사유 로그) 없음.\n"
                . "자세한 내용: qa/member_grades_15_plan.md §6."],

            ['등급', 'low', 'todo', '회원 등급 링을 나머지 화면에도 적용 (댓글·글 목록·채팅·친구 등 약 50곳)',
                "현재 적용: 상단 내 사진, 마이페이지, 다른 회원 프로필, 사용자 팝업. 남은 곳은 점진 적용.\n"
                . "방법: UserAvatar 컴포넌트로 교체 + 해당 API 의 user select 에 lifetime_points 추가(없으면 링 생략됨). 작은 아바타(36px 미만)는 링을 그리지 않음. 우선순위: 댓글, 게시글 상세 작성자, 친구, 채팅 참가자, 장터/공동구매 판매자·주최자 순. 채팅 메시지 목록은 아바타 자체가 없음."],
        ];

        foreach ($rows as [$cat, $pri, $st, $title, $detail]) {
            if (DB::table('admin_todos')->where('title', $title)->exists()) continue;
            $sort += 10;
            DB::table('admin_todos')->insert([
                'title' => $title, 'detail' => $detail, 'category' => $cat, 'priority' => $pri, 'status' => $st,
                'sort_order' => $sort, 'done_at' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // 사용자가 상태/내용을 고쳤을 수 있어 되돌릴 때 삭제하지 않는다
    }
};
