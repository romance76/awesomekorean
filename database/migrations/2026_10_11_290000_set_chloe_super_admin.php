<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Kay 결정(2026-10-11): Chloe(회원 번호 2905)는 최고관리자(super_admin)가 맞다.
// 안전장치: 번호가 2905 이면서 이름·닉네임·이메일 중 하나에 'chloe' 또는 '클로에'가 들어 있을 때만 바꾼다
// (번호만 같고 다른 사람이면 아무것도 안 함). 이미 super_admin 이면 건너뜀. 여러 번 돌려도 안전, 기록은 감사 로그에 남긴다.
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('users')) return;

        $u = DB::table('users')->where('id', 2905)->first(['id', 'name', 'nickname', 'email', 'role']);
        if (!$u || $u->role === 'super_admin') return;

        $hay = mb_strtolower(($u->name ?? '') . ' ' . ($u->nickname ?? '') . ' ' . ($u->email ?? ''));
        if (!str_contains($hay, 'chloe') && !str_contains($hay, '클로에')) return;

        DB::table('users')->where('id', 2905)->update(['role' => 'super_admin', 'updated_at' => now()]);

        try {
            if (DB::getSchemaBuilder()->hasTable('admin_audit_log')) DB::table('admin_audit_log')->insert([
                'admin_id' => null,
                'action' => 'MIGRATION users/2905 role',
                'target_type' => 'users',
                'target_id' => 2905,
                'before_value' => null,
                'after_value' => json_encode(['http_status' => 200, 'fields' => ['role'], 'reason' => 'Kay 결정: Chloe 최고관리자', 'from' => $u->role, 'to' => 'super_admin'], JSON_UNESCAPED_UNICODE),
                'note' => 'migration',
                'ip' => null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // 기록 실패는 권한 변경 자체에 영향 없음
        }
    }

    public function down(): void
    {
        // 권한을 자동으로 되돌리지 않는다. (바꾸려면 관리자 › 회원관리에서)
    }
};
