<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 10라운드: 회원 수정 검증·정지 칸 보호, 신고 접수 검증·숨김 복구, 꺼진 게시판 반영.
 * 마이그레이션이 MySQL/MariaDB 전용이라 MariaDB 테스트 DB 로 실행:
 *   DB_CONNECTION=mysql DB_DATABASE=<테스트DB> DB_USERNAME=.. DB_PASSWORD=.. php artisan test tests/Feature/AdminRound10Test.php
 */
class AdminRound10Test extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role])->save();
        return $u;
    }

    private function reauth(User $u): void
    {
        // 위험 동작 전 비밀번호 재확인(reauth) 통과 상태로
        cache()->put('admin_reauth:' . $u->id, now()->timestamp, 600);
    }

    // ── 회원 정보 수정 ──
    public function test_admin_can_save_full_user_object_without_role_change(): void
    {
        $admin = $this->staff('admin');
        $u = User::factory()->create(['name' => '홍길동']);
        $payload = array_merge($u->fresh()->toArray(), ['name' => '김철수']);
        $this->actingAs($admin, 'api')->putJson("/api/admin/users/{$u->id}", $payload)->assertOk();
        $this->assertSame('김철수', $u->fresh()->name);
    }

    public function test_update_user_validates_email_and_length(): void
    {
        $admin = $this->staff('admin');
        $other = User::factory()->create();
        $u = User::factory()->create();
        $this->actingAs($admin, 'api')->putJson("/api/admin/users/{$u->id}", ['email' => 'not-an-email'])->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson("/api/admin/users/{$u->id}", ['email' => $other->email])
            ->assertStatus(422)->assertJsonPath('errors.email.0', '이미 다른 회원이 쓰고 있는 이메일이에요.');
        $this->actingAs($admin, 'api')->putJson("/api/admin/users/{$u->id}", ['name' => str_repeat('가', 51)])->assertStatus(422);
    }

    public function test_update_user_cannot_change_ban_flag(): void
    {
        $admin = $this->staff('admin');
        $u = User::factory()->create();
        $this->actingAs($admin, 'api')->putJson("/api/admin/users/{$u->id}", ['is_banned' => true])->assertStatus(422);
        $this->assertFalse((bool) $u->fresh()->is_banned);
    }

    public function test_only_super_admin_changes_role(): void
    {
        $admin = $this->staff('admin');
        $super = $this->staff('super_admin');
        $u = User::factory()->create();
        $this->actingAs($admin, 'api')->putJson("/api/admin/users/{$u->id}", ['role' => 'moderator'])->assertStatus(403);
        $this->actingAs($super, 'api')->putJson("/api/admin/users/{$u->id}", ['role' => 'moderator'])->assertOk();
        $this->assertSame('moderator', $u->fresh()->role);
    }

    public function test_reset_password_enforces_signup_rules(): void
    {
        $super = $this->staff('super_admin');
        $this->reauth($super);
        $u = User::factory()->create();
        $this->actingAs($super, 'api')->postJson("/api/admin/users/{$u->id}/reset-password", ['password' => 'short'])->assertStatus(422);
        $res = $this->actingAs($super, 'api')->postJson("/api/admin/users/{$u->id}/reset-password", [])->assertOk();
        $tmp = $res->json('data.temporary_password');
        $this->assertMatchesRegularExpression('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{12}$/', $tmp);
    }

    // ── 신고 ──
    private function makePost(User $owner, ?Board $board = null): Post
    {
        $board ??= Board::create(['name' => '자유', 'slug' => 'free' . uniqid(), 'is_active' => true]);
        return Post::create(['board_id' => $board->id, 'user_id' => $owner->id, 'title' => 't', 'content' => 'c']);
    }

    public function test_report_store_validates_target_self_and_duplicates(): void
    {
        $author = User::factory()->create();
        $reporter = User::factory()->create();
        $post = $this->makePost($author);

        $this->actingAs($reporter, 'api')->postJson('/api/reports', ['reportable_type' => 'nope', 'reportable_id' => $post->id, 'reason' => 'spam'])->assertStatus(422);
        $this->actingAs($reporter, 'api')->postJson('/api/reports', ['reportable_type' => 'post', 'reportable_id' => 999999, 'reason' => 'spam'])->assertStatus(404);
        $this->actingAs($author, 'api')->postJson('/api/reports', ['reportable_type' => 'post', 'reportable_id' => $post->id, 'reason' => 'spam'])->assertStatus(422);
        $this->actingAs($reporter, 'api')->postJson('/api/reports', ['reportable_type' => 'post', 'reportable_id' => $post->id, 'reason' => str_repeat('x', 300)])->assertStatus(422);

        $this->actingAs($reporter, 'api')->postJson('/api/reports', ['reportable_type' => 'post', 'reportable_id' => $post->id, 'reason' => 'spam'])->assertStatus(201);
        $this->assertSame(Post::class, Report::first()->reportable_type);
        $this->actingAs($reporter, 'api')->postJson('/api/reports', ['reportable_type' => Post::class, 'reportable_id' => $post->id, 'reason' => 'spam'])->assertStatus(409);
    }

    public function test_reopening_report_restores_hidden_content(): void
    {
        $admin = $this->staff('admin');
        $post = $this->makePost(User::factory()->create());
        $report = Report::create(['reporter_id' => User::factory()->create()->id, 'reportable_type' => Post::class, 'reportable_id' => $post->id, 'reason' => 'spam', 'status' => 'pending']);

        $this->actingAs($admin, 'api')->putJson("/api/admin/reports/{$report->id}", ['status' => 'resolved', 'hide_content' => true])->assertOk();
        $this->assertTrue((bool) $post->fresh()->is_hidden);
        $this->actingAs($admin, 'api')->putJson("/api/admin/reports/{$report->id}", ['status' => 'pending'])->assertOk();
        $this->assertFalse((bool) $post->fresh()->is_hidden);
    }

    public function test_moderator_cannot_hide_staff_post(): void
    {
        $mod = $this->staff('moderator');
        $adminPost = $this->makePost($this->staff('admin'));
        $userPost = $this->makePost(User::factory()->create());
        $this->actingAs($mod, 'api')->postJson("/api/admin/posts/{$adminPost->id}/hide")->assertStatus(403);
        $this->actingAs($mod, 'api')->postJson("/api/admin/posts/{$userPost->id}/hide")->assertOk();
        $this->assertFalse((bool) $adminPost->fresh()->is_hidden);
        $this->assertTrue((bool) $userPost->fresh()->is_hidden);
    }

    // ── 꺼진 게시판 ──
    public function test_inactive_board_posts_disappear_for_members(): void
    {
        $member = User::factory()->create();
        $on = Board::create(['name' => '켜짐', 'slug' => 'on', 'is_active' => true]);
        $off = Board::create(['name' => '꺼짐', 'slug' => 'off', 'is_active' => false]);
        $visible = $this->makePost(User::factory()->create(), $on);
        $gone = $this->makePost(User::factory()->create(), $off);

        $ids = collect($this->getJson('/api/posts')->assertOk()->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains($visible->id));
        $this->assertFalse($ids->contains($gone->id));
        $this->getJson("/api/posts/{$gone->id}")->assertStatus(404);
        $this->getJson("/api/posts/{$visible->id}")->assertOk();

        $member->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($member, 'api')->postJson('/api/posts', ['board_id' => $off->id, 'title' => 't', 'content' => 'c'])->assertStatus(422);
    }
}
