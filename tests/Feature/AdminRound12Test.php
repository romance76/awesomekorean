<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 12라운드: 반복 일정 수정 시 다음 실행 시각 재계산·값 검증, 경품 날짜 논리, 응모 중 즉시 추첨 확인,
 * 회원 이벤트 보상 포인트 상한, 소유권 거절 사유 길이. MariaDB 테스트 DB 로 실행.
 */
class AdminRound12Test extends TestCase
{
    use RefreshDatabase;

    private function super(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'super_admin'])->save();
        return $u;
    }

    private function scheduleBody(array $over = []): array
    {
        return array_merge([
            'name' => '주간 추첨', 'title_template' => '{n}회 주간 추첨', 'prize_name' => '상품권',
            'repeat_unit' => 'weekly', 'weekday' => 1, 'start_time' => '09:00', 'duration_hours' => 24,
        ], $over);
    }

    public function test_schedule_rule_change_recomputes_next_run(): void
    {
        $s = $this->super();
        $id = $this->actingAs($s, 'api')->postJson('/api/admin/sweepstakes-schedules', $this->scheduleBody())->assertStatus(201)->json('data.id');
        $before = Carbon::parse(DB::table('sweepstakes_schedules')->where('id', $id)->value('next_run_at'), 'UTC')->setTimezone('America/New_York');
        $this->assertSame(1, $before->dayOfWeek);

        $this->actingAs($s, 'api')->putJson("/api/admin/sweepstakes-schedules/{$id}", $this->scheduleBody(['weekday' => 4, 'start_time' => '18:30']))->assertOk();
        $after = Carbon::parse(DB::table('sweepstakes_schedules')->where('id', $id)->value('next_run_at'), 'UTC')->setTimezone('America/New_York');
        $this->assertSame(4, $after->dayOfWeek);
        $this->assertSame('18:30', $after->format('H:i'));
    }

    public function test_schedule_bad_values_are_422_not_500(): void
    {
        $s = $this->super();
        $this->actingAs($s, 'api')->postJson('/api/admin/sweepstakes-schedules', $this->scheduleBody(['first_start_at' => 'garbage']))->assertStatus(422);
        $this->actingAs($s, 'api')->postJson('/api/admin/sweepstakes-schedules', $this->scheduleBody(['first_start_at' => '2020-01-01 09:00']))->assertStatus(422);
        $id = $this->actingAs($s, 'api')->postJson('/api/admin/sweepstakes-schedules', $this->scheduleBody())->json('data.id');
        DB::table('sweepstakes_schedules')->where('id', $id)->update(['runs_done' => 3]);
        $this->actingAs($s, 'api')->putJson("/api/admin/sweepstakes-schedules/{$id}", $this->scheduleBody(['total_runs' => 2]))->assertStatus(422);
    }

    private function sweepBody(array $over = []): array
    {
        return array_merge(['title' => '추첨', 'prize_name' => '상품', 'start_at' => now()->addDay()->toDateTimeString(), 'end_at' => now()->addDays(3)->toDateTimeString()], $over);
    }

    public function test_sweepstakes_date_logic(): void
    {
        $s = $this->super();
        $this->actingAs($s, 'api')->postJson('/api/admin/sweepstakes', $this->sweepBody(['status' => 'active', 'start_at' => now()->subDays(3)->toDateTimeString(), 'end_at' => now()->subDay()->toDateTimeString()]))->assertStatus(422);
        $id = $this->actingAs($s, 'api')->postJson('/api/admin/sweepstakes', $this->sweepBody())->assertOk()->json('data.id');
        // 마감만 시작보다 앞으로 바꾸면 거절
        $this->actingAs($s, 'api')->putJson("/api/admin/sweepstakes/{$id}", ['end_at' => now()->toDateTimeString()])->assertStatus(422);
    }

    public function test_early_draw_needs_explicit_confirmation(): void
    {
        $s = $this->super();
        $id = DB::table('sweepstakes')->insertGetId(['title' => '진행중', 'prize_name' => 'p', 'start_at' => now()->subDay(), 'end_at' => now()->addDay(), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($s, 'api')->postJson("/api/admin/sweepstakes/{$id}/select-winner")->assertStatus(409)->assertJsonPath('code', 'early_draw');
        // early=true 면 확인 단계는 통과 (응모자가 없어 추첨 자체는 422)
        $this->assertNotSame(409, $this->actingAs($s, 'api')->postJson("/api/admin/sweepstakes/{$id}/select-winner", ['early' => true])->status());
    }

    public function test_member_event_reward_points_capped(): void
    {
        $u = User::factory()->create();
        $u->forceFill(['email_verified_at' => now()])->save();
        $body = ['title' => '모임', 'start_date' => now()->addDay()->toDateString(), 'reward_points' => 999999];
        $this->actingAs($u, 'api')->postJson('/api/events', $body)->assertStatus(422)->assertJsonValidationErrors('reward_points');
        $this->actingAs($u, 'api')->postJson('/api/events', array_merge($body, ['reward_points' => 50]))->assertSuccessful();
    }

    public function test_claim_reject_long_notes_is_422(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin, 'api')->postJson('/api/admin/claims/1/reject', ['notes' => str_repeat('x', 5000)])->assertStatus(422);
    }
}
