<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Support\SiteFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 관리자 사이트 설정 "껍데기" 스위치 실동작: 점검 모드·회원가입 허용·이메일 인증 필수·비밀번호 최소 길이.
 * MariaDB 테스트 DB 로 실행 (AdminRound11Test 주석 참고).
 */
class SiteFlagsTest extends TestCase
{
    use RefreshDatabase;

    private function set(string $key, string $value): void
    {
        SiteSetting::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_defaults_keep_the_site_open(): void
    {
        $f = SiteFlags::all();
        $this->assertTrue($f['allow_signup']);
        $this->assertTrue($f['require_email_verify']);
        $this->assertFalse($f['maintenance_mode']);
        $this->assertSame(8, $f['min_password_length']);
    }

    public function test_maintenance_blocks_members_but_not_staff_login_or_admin_api(): void
    {
        $member = $this->userWithRole('user');
        $admin = $this->userWithRole('super_admin');
        $this->set('maintenance_mode', '1');
        $this->set('maintenance_reason', '밤새 점검합니다');

        // 비회원/일반 회원 API → 503 + 점검 안내
        $this->getJson('/api/news')->assertStatus(503)->assertJson(['maintenance' => true, 'message' => '밤새 점검합니다']);
        $this->actingAs($member, 'api')->getJson('/api/news')->assertStatus(503)->assertJson(['maintenance' => true]);

        // 항상 열려 있는 것: 공개 설정(점검 안내 포함), 로그인 화면용 API, 관리자 API
        $this->getJson('/api/settings/public')->assertOk()->assertJsonPath('data.maintenance_mode', '1');
        $this->actingAs($admin, 'api')->getJson('/api/admin/settings')->assertOk();
        $this->assertNotSame(503, $this->actingAs($admin, 'api')->getJson('/api/news')->status());

        // 일반 회원은 로그인도 막히고(503), 관리자는 로그인된다
        $this->postJson('/api/login', ['email' => $member->email, 'password' => 'password'])->assertStatus(503)->assertJson(['maintenance' => true]);
        $this->assertNotSame(503, $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'password'])->status());
    }

    public function test_turning_maintenance_off_from_admin_releases_immediately(): void
    {
        $admin = $this->userWithRole('super_admin');
        $this->set('maintenance_mode', '1');
        $this->getJson('/api/news')->assertStatus(503);   // 캐시에 "켜짐" 이 담긴다

        $this->actingAs($admin, 'api')->postJson('/api/admin/settings/site', ['maintenance_mode' => false])
            ->assertOk()->assertJsonPath('data.maintenance_mode', false);

        $this->assertNotSame(503, $this->getJson('/api/news')->status());   // 바로 풀림
        $this->getJson('/api/settings/public')->assertOk()->assertJsonPath('data.maintenance_mode', '0');
    }

    public function test_signup_can_be_closed_without_touching_existing_login(): void
    {
        $member = $this->userWithRole('user');
        $this->set('allow_signup', '0');

        $this->postJson('/api/register', ['name' => '신규', 'email' => 'new@example.com', 'password' => 'Abcdefg1', 'password_confirmation' => 'Abcdefg1'])
            ->assertStatus(403)->assertJson(['code' => 'signup_closed']);
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);

        // 기존 회원 로그인은 그대로
        $this->assertNotSame(403, $this->postJson('/api/login', ['email' => $member->email, 'password' => 'password'])->status());

        // 다시 열면 가입 절차(검증)로 넘어간다
        $this->set('allow_signup', '1');
        $this->postJson('/api/register', [])->assertStatus(422);
    }

    public function test_password_min_length_setting_is_used_by_register(): void
    {
        $this->set('min_password_length', '12');
        $short = $this->postJson('/api/register', ['name' => '가입', 'email' => 'pw@example.com', 'password' => 'Abcdefgh1x', 'password_confirmation' => 'Abcdefgh1x']);
        $short->assertStatus(422)->assertJsonValidationErrors(['password']);

        $long = $this->postJson('/api/register', ['name' => '가입', 'email' => 'pw@example.com', 'password' => 'Abcdefghij1x', 'password_confirmation' => 'Abcdefghij1x']);
        $long->assertJsonMissingValidationErrors(['password']);
    }

    public function test_unverified_member_can_write_only_when_verification_is_required(): void
    {
        $u = User::factory()->unverified()->create(['role' => 'user']);

        $this->actingAs($u, 'api')->postJson('/api/posts', [])->assertStatus(403);   // 기본: 인증 필수

        $this->set('require_email_verify', '0');
        $this->assertNotSame(403, $this->actingAs($u, 'api')->postJson('/api/posts', [])->status());   // 끄면 글쓰기 검증 단계로 넘어감(422 등)
    }

    public function test_site_settings_reject_unsafe_password_length_and_bulk_save_is_checked_too(): void
    {
        $s = $this->userWithRole('super_admin');
        foreach ([1, 3, 6, 7, 65, 'abc'] as $bad) {
            $this->actingAs($s, 'api')->postJson('/api/admin/settings/site', ['min_password_length' => $bad])->assertStatus(422);
        }
        $this->actingAs($s, 'api')->postJson('/api/admin/settings/site', ['min_password_length' => 10])->assertOk();
        $this->assertSame('10', SiteSetting::where('key', 'min_password_length')->value('value'));
        $this->assertSame(10, SiteFlags::minPasswordLength());

        // 일괄 저장(PUT)으로는 점검 모드에 엉뚱한 값을 넣을 수 없다
        $this->actingAs($s, 'api')->putJson('/api/admin/settings', ['maintenance_mode' => 'maybe'])->assertStatus(422);
        $this->assertNotSame('maybe', SiteSetting::where('key', 'maintenance_mode')->value('value'));
    }
}
