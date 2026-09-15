<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 버그 제보 이벤트 포인트가 사이트 전체 포인트 경제(게임 레벨업 3P,
// 출석 5~50P, 댓글 3P, 글 5P 등)에 비해 지나치게 높게 책정돼 있어
// 하향 조정. 라이브 DB에는 이미 500/100/2000에서 150/30/400으로 한 번
// 수정된 값이 들어있어(OfficialEventSeeder 원본과 다름), 시더가 아니라
// 이 마이그레이션으로 기존 행을 직접 갱신해야 실제로 반영됨.
return new class extends Migration {
    public function up(): void
    {
        $content = "사이트를 이용하다가 이상한 점을 발견하셨나요?\n"
            . "에러가 나거나, 클릭이 안 되거나, 화면이 이상하게 보이는 부분을\n"
            . "캡처해서 올려주시면 포인트를 드립니다!\n\n"
            . "참여 방법:\n"
            . "1. 문제가 생긴 화면을 캡처\n"
            . "2. 이 게시물에 댓글로 사진 + 설명 등록\n"
            . "3. 확인 후 포인트 지급\n\n"
            . "보상:\n"
            . "- 처음 제보한 버그: 50 포인트\n"
            . "- 이미 제보된 버그 추가 확인: 10 포인트 (1인 1일 최대 2건)\n"
            . "- 중요 버그 (결제/로그인 등): 최대 150 포인트";

        DB::table('events')
            ->where('title', '🐛 버그를 잡아라! 오류 제보 이벤트')
            ->update([
                'content' => $content,
                'reward_points' => 50,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // 포인트 하향 조정은 되돌릴 필요 없음 (원래 값이 이미 과도했음)
    }
};
