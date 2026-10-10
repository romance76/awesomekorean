<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 10라운드에서 고친 높음 3건을 할 일 목록에서 완료로 표시한다(제목 앞부분으로 찾음, 여러 번 돌려도 안전).
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $now = now();
        $done = [
            '회원 정보 수정 검증 + 정지 칸 보호%' => '이메일 형식·중복, 이름·별명 길이 검증과 한국어 오류. 수정 저장으로는 정지 칸을 못 바꾸고 정지 버튼(사유 기록)으로만. 등급은 바뀐 경우에만 최고관리자 확인. 비밀번호 초기화는 최고관리자 전용 + 가입과 같은 규칙, 임시 비밀번호도 규칙 충족.',
            '신고 기능 정리: 대상 검증%' => '신고 종류 이름 통일(전체 이름 저장), 없는 대상 404·본인 글 422·처리 전 중복 409, 사유 200자·내용 2000자 제한. 다시 대기로 돌리면 신고로 숨긴 글 복구(이력 남김). 운영자는 운영진 글을 숨기지 못함. 운영자 권한 범위(채팅 삭제·강퇴)는 권한 세부 정리 항목에서.',
            '게시판 비활성화가 회원 화면에 반영되지 않는 문제%' => '게시판 관리에 켜기/끄기 버튼 추가. 꺼진 게시판 글은 전체 목록·검색·글보기(작성자·운영진 제외)·글쓰기에서 빠짐.',
        ];
        foreach ($done as $like => $note) {
            DB::table('admin_todos')->where('title', 'like', $like)->where('status', '!=', 'done')
                ->update(['status' => 'done', 'done_at' => $now, 'updated_at' => $now,
                    'detail' => DB::raw("CONCAT(COALESCE(detail, ''), '\n[완료 10/11] " . addslashes($note) . "')")]);
        }
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
