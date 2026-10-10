<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 11라운드: 약관·회사·사이트·SEO 설정 저장 검증, 경품 수령 확인·안 보낸 경품 모아 보기·오래 안 보낸 경품 알림.
 * MariaDB 테스트 DB 로 실행 (AdminRefundPaymentTest 주석 참고).
 */
class AdminRound11Test extends TestCase
{
    use RefreshDatabase;

    private function super(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'super_admin'])->save();
        return $u;
    }

    // ── 설정 ──
    public function test_terms_type_empty_and_size_are_checked(): void
    {
        $s = $this->super();
        $this->actingAs($s, 'api')->postJson('/api/admin/settings/terms/refund', ['content' => '내용'])->assertStatus(404);
        $this->actingAs($s, 'api')->postJson('/api/admin/settings/terms/terms', ['content' => '<p>  </p>'])->assertStatus(422);
        $this->actingAs($s, 'api')->postJson('/api/admin/settings/terms/terms', ['content' => str_repeat('가', 30000)])->assertStatus(422);
        $this->actingAs($s, 'api')->postJson('/api/admin/settings/terms/privacy', ['content' => '<p>개인정보처리방침</p>'])->assertOk();
        $this->assertSame('<p>개인정보처리방침</p>', SiteSetting::where('key', 'privacy_page')->value('value'));
    }

    public function test_site_settings_values_are_checked_and_unknown_keys_ignored(): void
    {
        $s = $this->super();
        $this->actingAs($s, 'api')->postJson('/api/admin/settings/site', ['max_upload_mb' => 'abc'])->assertStatus(422);
        $this->actingAs($s, 'api')->postJson('/api/admin/settings/site', ['min_password_length' => 3])->assertStatus(422);
        $this->actingAs($s, 'api')->postJson('/api/admin/settings/site', ['allowed_file_types' => 'jpg;<script>'])->assertStatus(422);
        $this->actingAs($s, 'api')->postJson('/api/admin/settings/site', ['maintenance_until' => 'not a date'])->assertStatus(422);
        $this->actingAs($s, 'api')->postJson('/api/admin/settings/site', ['allow_signup' => false, 'allowed_file_types' => 'JPG, png', 'points_signup' => '999999'])->assertOk();
        $this->assertSame('0', SiteSetting::where('key', 'allow_signup')->value('value'));
        $this->assertSame('jpg,png', SiteSetting::where('key', 'allowed_file_types')->value('value'));
        $this->assertNull(SiteSetting::where('key', 'points_signup')->value('value'));
    }

    public function test_company_and_seo_check_only_changed_fields(): void
    {
        $s = $this->super();
        SiteSetting::create(['key' => 'logo_url', 'value' => 'storage/old-logo.png']);   // 옛 데이터(앞에 / 없음)
        // 안 바뀐 옛 값은 그대로 두고 다른 칸 저장 가능
        $this->actingAs($s, 'api')->postJson('/api/admin/settings/company', ['logo_url' => 'storage/old-logo.png', 'site_name' => '어썸코리안'])->assertOk();
        $this->actingAs($s, 'api')->postJson('/api/admin/settings/company', ['logo_url' => 'javascript:alert(1)'])->assertStatus(422);
        $this->actingAs($s, 'api')->postJson('/api/admin/settings/company', ['email' => 'nope'])->assertStatus(422);
        $this->actingAs($s, 'api')->postJson('/api/admin/settings/seo', ['og_image' => 'javascript:x'])->assertStatus(422);
        $this->actingAs($s, 'api')->postJson('/api/admin/settings/seo', ['meta_title' => '<b>제목</b>'])->assertStatus(422);
        $this->actingAs($s, 'api')->postJson('/api/admin/settings/seo', ['meta_title' => '어썸코리안'])->assertOk();
        $this->assertSame('어썸코리안', SiteSetting::where('key', 'seo_meta_title')->value('value'));
    }

    // ── 경품 ──
    private function claim(User $winner, string $status, int $daysAgo = 0): int
    {
        $sid = DB::table('sweepstakes')->insertGetId([
            'title' => '10월 추첨', 'prize_name' => '상품권 $50', 'start_at' => now()->subDays(10), 'end_at' => now()->subDays($daysAgo + 1),
            'status' => 'winner_selected', 'winner_selected_at' => now()->subDays($daysAgo), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('sweepstakes_prize_claims')->insertGetId([
            'sweepstakes_id' => $sid, 'user_id' => $winner->id, 'rank' => 1, 'contact_confirmed_at' => now(),
            'delivery_status' => $status, 'sent_at' => $status === 'sent' ? now() : null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_winner_confirms_receipt_only_after_sent(): void
    {
        $w = User::factory()->create();
        $pending = $this->claim($w, 'pending');
        $sent = $this->claim($w, 'sent');

        $res = $this->actingAs($w, 'api')->getJson('/api/me/prize-claims')->assertOk();
        $this->assertSame([$sent], collect($res->json('sent'))->pluck('id')->all());

        $this->actingAs($w, 'api')->postJson("/api/me/prize-claims/{$pending}/received")->assertStatus(422);
        $this->actingAs($w, 'api')->postJson("/api/me/prize-claims/{$sent}/received")->assertOk();
        $this->assertSame('confirmed', DB::table('sweepstakes_prize_claims')->where('id', $sent)->value('delivery_status'));
        $this->assertTrue(DB::table('sweepstakes_delivery_logs')->where('claim_id', $sent)->where('action', 'received_by_winner')->exists());
        $this->assertSame([], $this->actingAs($w, 'api')->getJson('/api/me/prize-claims')->json('sent'));

        // 다른 회원 건은 못 누름
        $this->actingAs(User::factory()->create(), 'api')->postJson("/api/me/prize-claims/{$pending}/received")->assertStatus(404);
    }

    public function test_admin_pending_list_and_daily_overdue_reminder(): void
    {
        $s = $this->super();
        $w = User::factory()->create();
        $old = $this->claim($w, 'pending', 5);
        $this->claim(User::factory()->create(), 'confirmed', 9);

        $rows = $this->actingAs($s, 'api')->getJson('/api/admin/sweepstakes-delivery/pending')->assertOk()->json('data');
        $this->assertSame([$old], collect($rows)->pluck('id')->all());
        $this->assertSame(5, $rows[0]['days']);

        $this->artisan('sweepstakes:remind-overdue-deliveries')->assertSuccessful();
        $this->artisan('sweepstakes:remind-overdue-deliveries')->assertSuccessful();   // 같은 날 두 번 → 한 번만
        $this->assertSame(1, Notification::where('user_id', $s->id)->where('type', 'prize_delivery_overdue')->count());
    }
}
