<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AutoSeedPosts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 커뮤니티·Q&A 자동 게시: 하루 개수 범위, 회원 수 상한 정지, 더미 회원만 글쓴이, 같은 출처 중복 방지.
 * 마이그레이션이 MySQL/MariaDB 전용이라 MariaDB 테스트 DB 로 실행(AdminRefundPaymentTest 주석 참고).
 */
class AutoSeedPostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_plan_is_within_range_and_slots_are_sorted(): void
    {
        AutoSeedPosts::saveConfig(['daily_min' => 5, 'daily_max' => 7]);
        $s = AutoSeedPosts::state();
        foreach (AutoSeedPosts::KINDS as $k) {
            $this->assertGreaterThanOrEqual(5, $s['plan'][$k]['target']);
            $this->assertLessThanOrEqual(7, $s['plan'][$k]['target']);
            $this->assertCount($s['plan'][$k]['target'], $s['plan'][$k]['slots']);
            $sorted = $s['plan'][$k]['slots'];
            sort($sorted);
            $this->assertSame($sorted, $s['plan'][$k]['slots']);
        }
    }

    public function test_stops_when_member_count_exceeds_cap_or_disabled(): void
    {
        User::factory()->count(3)->create();
        AutoSeedPosts::saveConfig(['enabled' => true, 'member_cap' => 3000]);
        $this->assertNull(AutoSeedPosts::blockReason());

        AutoSeedPosts::saveConfig(['member_cap' => 2]);   // 회원 3명 > 상한 2
        $this->assertSame('member_cap', AutoSeedPosts::blockReason());

        AutoSeedPosts::saveConfig(['member_cap' => 3]);   // 상한과 같으면 아직 계속
        $this->assertNull(AutoSeedPosts::blockReason());

        AutoSeedPosts::saveConfig(['enabled' => false]);
        $this->assertSame('disabled', AutoSeedPosts::blockReason());
    }

    public function test_author_pool_is_dummy_members_only(): void
    {
        $dummy = User::factory()->create(['email' => 'someone@test.com', 'role' => 'user']);
        User::factory()->create(['email' => 'real@gmail.com', 'role' => 'user']);
        User::factory()->create(['email' => 'boss@test.com', 'role' => 'super_admin']);
        User::factory()->create(['email' => 'money-ops@awesomekorean.local', 'role' => 'user']);

        $this->assertSame([$dummy->id], AutoSeedPosts::authorPool());
    }

    public function test_same_source_is_recognized_even_with_query_or_host_variant(): void
    {
        AutoSeedPosts::record('qa', 1, 1, '제목', 'https://www.reddit.com/r/korea/comments/abc/x/?utm_source=a', '/qa/1');
        $this->assertTrue(AutoSeedPosts::isSeen('https://old.reddit.com/r/korea/comments/abc/x'));
        $this->assertFalse(AutoSeedPosts::isSeen('https://www.reddit.com/r/korea/comments/zzz/y/'));
    }
}
