<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 2026-10-14 (Kay 지시): 게임과 안심서비스는 서비스로 실행하지 않으므로 할 일 목록에서 뺀다.
// 삭제하지 않고 분류를 '제외'로, 상태를 완료로 바꿔 "해야 할 것"에서만 사라지게 한다(나중에 서비스를 열면 내용 그대로 다시 볼 수 있음).
// 제목이 같은(LIKE) 항목만 대상, 메모에 표시가 있으면 건너뜀(여러 번 돌려도 안전), 데이터 삭제 없음.
return new class extends Migration
{
    private const MARK = '[10/14 제외]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $now = now();
        $note = '게임·안심서비스는 서비스로 실행하지 않아 이 항목은 하지 않기로 함(제외, 완료 아님). 10/14 점검 4차에서 넣었던 이 서비스의 포인트 한도 코드도 다시 되돌렸음. 서비스를 다시 열 때 이 내용부터 다시 보면 됨.';
        foreach (['게임 포인트 지급 한도%', '안심서비스 체크인 포인트%'] as $like) {
            foreach (DB::table('admin_todos')->where('title', 'like', $like)->get(['id', 'detail', 'status', 'category']) as $r) {
                if (str_contains((string) $r->detail, self::MARK)) continue;
                DB::table('admin_todos')->where('id', $r->id)->update([
                    'category' => '제외', 'status' => 'done', 'done_at' => $r->status === 'done' ? DB::raw('done_at') : $now, 'updated_at' => $now,
                    'detail' => trim(($r->detail ?? '') . "\n" . self::MARK . ' ' . $note . ' (원래 분류: ' . $r->category . ')'),
                ]);
            }
        }
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
