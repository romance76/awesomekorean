<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// "비밀번호를 바꾸면 기존 로그인 전부 끊기" 항목을 완료 처리한다(없거나 이미 완료/메모 있으면 건드리지 않음).
return new class extends Migration
{
    private const MARK = '[10/7 확인]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;

        $row = DB::table('admin_todos')->where('title', '비밀번호를 바꾸면 기존 로그인 전부 끊기')->first();
        if (!$row || $row->status === 'done' || str_contains((string) $row->detail, self::MARK)) return;

        $note = '구현·시험 완료: 비밀번호를 바꾼 시각(users.password_changed_at)보다 먼저 발급된 로그인 토큰은 모두 거부(다른 기기 즉시 로그아웃, 갱신으로 우회 불가). '
              . '바꾸는 기기는 서버가 준 새 토큰으로 이어서 로그인 유지. 비밀번호 재설정(비밀번호 찾기)·관리자 초기화도 같은 방식으로 적용. 기존 회원은 값이 비어 있어 배포 때 아무도 로그아웃되지 않음.';

        DB::table('admin_todos')->where('id', $row->id)->update([
            'detail' => rtrim((string) $row->detail) . "\n" . self::MARK . ' ' . $note,
            'status' => 'done',
            'done_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // 진행 상황 기록이라 되돌리지 않음
    }
};
