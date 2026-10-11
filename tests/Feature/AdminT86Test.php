<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Support\AdminNotifier;
use App\Support\EmergencySwitches;
use App\Support\PointRules;
use App\Support\SweepstakesAutomation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 관리자 알림 딥링크 · 비상 스위치 · 시스템 상태 카드. (실행 환경: phpunit.xml 의 sqlite 메모리 DB)
 */
class AdminT86Test extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /** admin_audit_log 표는 운영 DB 에만 있고 마이그레이션이 없어서, 시험에서는 같은 모양으로 만들어 둔다 */
    private function ensureAuditTable(): void
    {
        if (Schema::hasTable('admin_audit_log')) return;
        Schema::create('admin_audit_log', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('admin_id')->nullable();
            $t->string('action', 100);
            $t->string('target_type', 50)->nullable();
            $t->unsignedBigInteger('target_id')->nullable();
            $t->text('before_value')->nullable();
            $t->text('after_value')->nullable();
            $t->string('note', 255)->nullable();
            $t->string('ip', 64)->nullable();
            $t->timestamp('created_at')->nullable();
        });
    }

    public function test_system_status_and_switches_are_super_admin_only(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin, 'api')->getJson('/api/admin/system-status')->assertStatus(403);
        $this->actingAs($admin, 'api')->getJson('/api/admin/emergency-switches')->assertStatus(403);
        $this->actingAs($admin, 'api')->putJson('/api/admin/emergency-switches/signup_bonus', ['paused' => true])->assertStatus(403);
        $this->assertFalse(EmergencySwitches::on('signup_bonus'));
    }

    public function test_system_status_returns_every_section(): void
    {
        $res = $this->actingAs($this->user('super_admin'), 'api')->getJson('/api/admin/system-status')->assertOk();
        $res->assertJsonPath('success', true);
        foreach (['level', 'alerts', 'queue', 'scheduler', 'disk', 'memory', 'deploy', 'generated_at'] as $k) {
            $res->assertJsonStructure(['data' => [$k]]);
        }
        $this->assertContains($res->json('data.level'), ['ok', 'warn', 'bad']);
        $this->assertNotNull($res->json('data.queue.state'));
    }

    public function test_scheduler_heartbeat_turns_red_only_after_three_minutes(): void
    {
        $file = storage_path('framework/scheduler-heartbeat');
        $had = is_file($file) ? file_get_contents($file) : null;
        try {
            file_put_contents($file, (string) (time() - 60));
            $this->assertSame('ok', \App\Support\SystemStatus::scheduler(null)['state']);
            file_put_contents($file, (string) (time() - 200));
            $this->assertSame('bad', \App\Support\SystemStatus::scheduler(null)['state']);
            \App\Support\SystemStatus::beat();
            $this->assertSame('ok', \App\Support\SystemStatus::scheduler(null)['state']);
        } finally {
            if ($had === null) @unlink($file); else file_put_contents($file, $had);
        }
    }

    public function test_toggle_saves_the_switch_and_writes_audit_log(): void
    {
        $this->ensureAuditTable();
        $s = $this->user('super_admin');
        $this->assertFalse(EmergencySwitches::on('auto_collect'));

        $this->actingAs($s, 'api')->putJson('/api/admin/emergency-switches/auto_collect', ['paused' => true])
            ->assertOk()->assertJsonPath('data.paused', true);
        $this->assertTrue(EmergencySwitches::on('auto_collect'));
        $this->assertDatabaseHas('admin_audit_log', ['admin_id' => $s->id, 'action' => 'EMERGENCY auto_collect', 'before_value' => 'running', 'after_value' => 'paused']);

        $list = $this->actingAs($s, 'api')->getJson('/api/admin/emergency-switches')->assertOk()->json('data');
        $this->assertCount(3, $list);
        $this->assertTrue(collect($list)->firstWhere('key', 'auto_collect')['paused']);

        $this->actingAs($s, 'api')->putJson('/api/admin/emergency-switches/auto_collect', ['paused' => false])->assertOk()->assertJsonPath('data.paused', false);
        $this->assertFalse(EmergencySwitches::on('auto_collect'));
        $this->assertDatabaseHas('admin_audit_log', ['action' => 'EMERGENCY auto_collect', 'before_value' => 'paused', 'after_value' => 'running']);

        $this->actingAs($s, 'api')->putJson('/api/admin/emergency-switches/nope', ['paused' => true])->assertStatus(404);
    }

    public function test_paused_signup_bonus_gives_no_points_but_signup_still_works(): void
    {
        $normal = PointRules::get('signup_bonus', 10);
        $pw = 'Abcdef12!xyz';

        EmergencySwitches::set('signup_bonus', true);
        $this->postJson('/api/register', ['name' => '멈춤회원', 'email' => 't86a@example.com', 'password' => $pw, 'password_confirmation' => $pw])->assertOk();
        $this->assertSame(0, (int) User::where('email', 't86a@example.com')->value('points'));

        EmergencySwitches::set('signup_bonus', false);
        $this->postJson('/api/register', ['name' => '정상회원', 'email' => 't86b@example.com', 'password' => $pw, 'password_confirmation' => $pw])->assertOk();
        $this->assertSame($normal, (int) User::where('email', 't86b@example.com')->value('points'));
    }

    public function test_paused_sweepstakes_schedules_create_nothing(): void
    {
        $s = $this->user('super_admin');
        $id = $this->actingAs($s, 'api')->postJson('/api/admin/sweepstakes-schedules', [
            'name' => '주간 추첨', 'title_template' => '{n}회 주간 추첨', 'prize_name' => '상품권',
            'repeat_unit' => 'weekly', 'weekday' => 1, 'start_time' => '09:00', 'duration_hours' => 24,
        ])->assertStatus(201)->json('data.id');
        DB::table('sweepstakes_schedules')->where('id', $id)->update(['status' => 'active', 'next_run_at' => now()->subMinutes(5)]);

        EmergencySwitches::set('sweepstakes_schedules', true);
        $this->assertSame(0, SweepstakesAutomation::runDueSchedules(true));
        $this->assertSame(0, SweepstakesAutomation::autoDraw());

        EmergencySwitches::set('sweepstakes_schedules', false);
        $this->assertSame(1, SweepstakesAutomation::runDueSchedules(true));
    }

    public function test_admin_notifier_sends_admin_links_only_to_allowed_ranks(): void
    {
        $mod = $this->user('moderator');
        $adm = $this->user('admin');
        $sup = $this->user('super_admin');
        $member = $this->user('user');

        $sent = AdminNotifier::send(2, 'banner_submitted', '새 광고 신청', '내용', '/admin/banners?status=pending', ['banner_id' => 7]);
        $this->assertSame(2, $sent);
        $this->assertFalse(Notification::where('user_id', $mod->id)->where('type', 'banner_submitted')->exists());
        $this->assertFalse(Notification::where('user_id', $member->id)->where('type', 'banner_submitted')->exists());
        foreach ([$adm, $sup] as $a) {
            $n = Notification::where('user_id', $a->id)->where('type', 'banner_submitted')->first();
            $this->assertNotNull($n);
            $this->assertSame('/admin/banners?status=pending', $n->data['url']);
            $this->assertSame(7, $n->data['banner_id']);
        }

        $this->assertSame(3, AdminNotifier::send(1, 'report_submitted', '새 신고', '내용', '/admin/security?tab=reports&status=pending'));
        $this->assertTrue(Notification::where('user_id', $mod->id)->where('type', 'report_submitted')->exists());
    }

    public function test_report_notification_opens_the_admin_report_list(): void
    {
        $admin = $this->user('admin');
        $reporter = $this->user('user');
        $target = $this->user('user');
        $this->actingAs($reporter, 'api')->postJson('/api/reports', ['reportable_type' => 'User', 'reportable_id' => $target->id, 'reason' => '욕설'])->assertStatus(201);
        $n = Notification::where('user_id', $admin->id)->where('type', 'report_submitted')->first();
        $this->assertNotNull($n);
        $this->assertStringStartsWith('/admin/security', $n->data['url']);
    }
}
