<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 현재 등록된 4개 이벤트(id 124~127)의 포인트 금액을 새 포인트 이코노미
 * (100P=$1) 기준에 맞춰 하향 조정하고, 종료일을 2026-11-30 로 통일 연장.
 * id 는 운영 DB의 실제 값을 확인 후 지정한 것이라 재실행해도 안전하도록
 * updateOrIgnore 대신 존재 여부를 먼저 확인한다.
 */
return new class extends Migration {
    private const UPDATES = [
        124 => [
            'reward_points' => 150,
            'content' => "사이트를 이용하다가 이상한 점을 발견하셨나요?\n에러가 나거나, 클릭이 안 되거나, 화면이 이상하게 보이는 부분을\n캡처해서 올려주시면 포인트를 드립니다!\n\n참여 방법:\n1. 문제가 생긴 화면을 캡처\n2. 이 게시물에 댓글로 사진 + 설명 등록\n3. 확인 후 포인트 지급\n\n보상:\n- 처음 제보한 버그: 150 포인트\n- 이미 제보된 버그 추가 확인: 30 포인트\n- 중요 버그 (결제/로그인 등): 최대 400 포인트",
        ],
        125 => [
            'reward_points' => 40,
            'content' => "오픈 기념! 부동산 리스팅 등록 시 포인트 2배!\n\n이벤트 혜택:\n- 리스팅 등록 횟수 제한 없음 (기간 내 무제한)\n- 리스팅 1건 등록 시 기본 20포인트 → 40포인트 지급\n- 매매/렌트/상업용 모두 적용\n\n참여 대상:\n- 부동산 에이전트\n- 집주인 직접 등록 (FSBO)\n- 렌트 매물 올리는 일반 회원 누구든지\n\n※ 부동산 에이전트로 활동하고 싶으신 분들도 환영합니다!\n   프로필 등록 후 바로 리스팅 시작하세요.",
        ],
        126 => [
            // 포인트 없는 이벤트, 내용 변경 없음
        ],
        127 => [
            'reward_points' => 20,
            'content' => "언제든지 들어와서 이야기 나눠요!\n혼자 있기 심심할 때, 궁금한 거 있을 때,\n그냥 수다 떨고 싶을 때 언제든지 환영합니다 😊\n\n오픈 채팅방 특징:\n- 24시간 365일 항상 열려 있어요\n- 로그인만 하면 바로 참여 가능\n- 한국어 / 영어 모두 OK\n- 미국 생활 정보, 일상 이야기, 무엇이든 OK\n- 채팅 참여만 해도 매일 포인트 지급!\n\n채팅 참여 포인트:\n- 첫 입장: 20 포인트\n- 매일 채팅 1회 이상: 5 포인트",
        ],
    ];

    private const NEW_END_DATE = '2026-11-30 23:59:59';

    public function up(): void
    {
        if (!Schema::hasTable('events')) return;

        foreach (self::UPDATES as $id => $fields) {
            if (!DB::table('events')->where('id', $id)->exists()) continue;
            DB::table('events')->where('id', $id)->update(array_merge($fields, [
                'end_date' => self::NEW_END_DATE,
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        // 데이터 되돌리기는 운영 DB 원본 값을 코드에 하드코딩하지 않고,
        // 안전하게 아무 것도 하지 않음 (원 문구/포인트는 별도 백업 필요 시 수동 복원).
    }
};
