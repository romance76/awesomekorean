<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * 로그인 유지 기간: 일반 회원은 로그인 후 30일, 운영자(admin/super_admin/moderator)는 180일까지 조용히 갱신되고,
 * 비밀번호를 바꾼 뒤에 발급된 토큰은(운영자도) 갱신되지 않는다.
 * 마이그레이션이 MySQL/MariaDB 전용이라 MariaDB 테스트 DB 로 실행:
 *   DB_CONNECTION=mysql DB_DATABASE=<테스트DB> DB_USERNAME=.. DB_PASSWORD=.. php artisan test tests/Feature/TokenRefreshWindowTest.php
 */
class TokenRefreshWindowTest extends TestCase
{
    use RefreshDatabase;

    /** 로그인한 지 $daysAgo 일 지나 이미 만료된(exp 가 한 시간 전인) 토큰 */
    private function expiredTokenFor(User $u, int $daysAgo): string
    {
        $iat = now()->subDays($daysAgo)->timestamp;
        // 정상 토큰의 내용을 가져와 발급 시각/만료 시각만 과거로 바꿔서 서명한다 (라이브러리 검사를 거치지 않고 직접 인코딩)
        $claims = JWTAuth::setToken(JWTAuth::fromUser($u))->getPayload()->toArray();
        $claims['iat'] = $iat;
        $claims['nbf'] = $iat;
        $claims['exp'] = now()->subHour()->timestamp;
        return JWTAuth::manager()->getJWTProvider()->encode($claims);
    }

    private function refreshWith(string $token)
    {
        return $this->withHeader('Authorization', 'Bearer ' . $token)->postJson('/api/auth/refresh');
    }

    public function test_member_can_refresh_within_30_days_but_not_after(): void
    {
        $u = User::factory()->create(['role' => 'user']);
        $this->refreshWith($this->expiredTokenFor($u, 10))->assertOk()->assertJsonStructure(['data' => ['token']]);
        $this->refreshWith($this->expiredTokenFor($u, 45))->assertStatus(401);
    }

    public function test_staff_can_refresh_up_to_180_days(): void
    {
        foreach (['moderator', 'admin', 'super_admin'] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->refreshWith($this->expiredTokenFor($u, 45))->assertOk();
            $this->refreshWith($this->expiredTokenFor($u, 120))->assertOk();
            $this->refreshWith($this->expiredTokenFor($u, 200))->assertStatus(401);
        }
    }

    public function test_password_change_and_ban_still_end_a_staff_session(): void
    {
        $u = User::factory()->create(['role' => 'admin']);
        $old = $this->expiredTokenFor($u, 5);
        $u->forceFill(['password_changed_at' => now()])->save();
        $this->refreshWith($old)->assertStatus(401);

        $b = User::factory()->create(['role' => 'moderator']);
        $tok = $this->expiredTokenFor($b, 5);
        $b->forceFill(['is_banned' => true])->save();
        $this->refreshWith($tok)->assertStatus(401);
    }
}
