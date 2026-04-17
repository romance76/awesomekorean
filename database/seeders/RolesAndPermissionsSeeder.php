<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * 역할·권한 시드 (2026-04-17 Phase 2-C 묶음 2)
 *
 * 역할 3종:
 *   - super_admin: 모든 권한
 *   - manager:     회원·콘텐츠·신고·포인트·분석·API 키 조회
 *   - moderator:   회원 조회·콘텐츠 검토/삭제·신고 처리·분석 조회
 *
 * 기존 users.role 컬럼은 유지 (롤백 안전장치). super_admin/admin/moderator 값을
 * Spatie 역할로 자동 매핑. user/business 는 역할 부여하지 않음 (일반 유저).
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 캐시 리셋
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── 권한 목록 (약 48개) ───
        $permissions = [
            // 회원
            'users.view', 'users.edit', 'users.delete',
            'users.role.change', 'users.ban', 'users.unban',
            'users.points.adjust', 'users.points.revoke',
            'users.session.terminate', 'users.password.reset',
            'users.login-history.view',

            // 콘텐츠
            'content.view', 'content.moderate', 'content.delete',
            'content.pin', 'content.hide', 'content.restore',
            'comments.delete',

            // 신고·보안
            'reports.view', 'reports.handle', 'reports.bulk',
            'ipbans.view', 'ipbans.manage', 'ipbans.cidr',
            'audit.view',

            // 포인트
            'points.rules.view', 'points.rules.edit',
            'points.adjust',

            // 결제
            'payments.view', 'payments.refund', 'payments.export',

            // 사이트 설정
            'site.settings.view', 'site.settings.edit',
            'site.pages.edit', 'site.footer.edit', 'site.faq.edit',

            // API·외부 연동
            'api.keys.view', 'api.keys.edit', 'api.keys.reveal',
            'integrations.manage',

            // 서버
            'server.view', 'server.manage', 'server.snapshot',
            'server.backup',

            // 알림·이메일
            'notifications.send', 'notifications.bulk',

            // 분석
            'analytics.view', 'analytics.advanced', 'analytics.export',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // ─── 역할 ───
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $manager    = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $moderator  = Role::firstOrCreate(['name' => 'moderator', 'guard_name' => 'web']);

        // super_admin: 전체
        $superAdmin->syncPermissions(Permission::all());

        // manager: 운영 전반 + 분석 고급 (서버·API reveal·settings.edit 제외)
        $managerPerms = [
            'users.view', 'users.edit', 'users.ban', 'users.unban',
            'users.points.adjust', 'users.points.revoke',
            'users.login-history.view',
            'content.view', 'content.moderate', 'content.delete',
            'content.pin', 'content.hide', 'content.restore',
            'comments.delete',
            'reports.view', 'reports.handle', 'reports.bulk',
            'ipbans.view', 'ipbans.manage',
            'audit.view',
            'points.rules.view', 'points.adjust',
            'payments.view', 'payments.export',
            'site.settings.view',
            'api.keys.view',
            'notifications.send', 'notifications.bulk',
            'analytics.view', 'analytics.advanced', 'analytics.export',
        ];
        $manager->syncPermissions($managerPerms);

        // moderator: 회원 조회 + 콘텐츠 검토/삭제 + 신고 처리 + 분석 조회
        $moderatorPerms = [
            'users.view',
            'content.view', 'content.moderate', 'content.delete',
            'content.pin', 'content.hide',
            'comments.delete',
            'reports.view', 'reports.handle',
            'analytics.view',
        ];
        $moderator->syncPermissions($moderatorPerms);

        // ─── 기존 users.role 컬럼 → Spatie 역할 매핑 ───
        if (DB::getSchemaBuilder()->hasColumn('users', 'role')) {
            $mapping = [
                'super_admin' => 'super_admin',
                'admin'       => 'manager',     // 기존 admin → manager (하위 호환)
                'moderator'   => 'moderator',
            ];
            foreach ($mapping as $old => $new) {
                User::where('role', $old)->get()->each(function (User $u) use ($new) {
                    if (!$u->hasRole($new)) {
                        $u->assignRole($new);
                    }
                });
            }
        }
    }
}
