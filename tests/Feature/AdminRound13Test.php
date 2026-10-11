<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 13라운드: 결제/오더 통계와 매출/결제 현황의 매출 기준 통일, 직접 결제 환불 안내. MariaDB 테스트 DB 로 실행.
 */
class AdminRound13Test extends TestCase
{
    use RefreshDatabase;

    private function super(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    public function test_payments_stats_match_revenue_summary(): void
    {
        $s = $this->super();
        $u = User::factory()->create();
        Payment::create(['user_id' => $u->id, 'kind' => 'points', 'amount' => 25, 'points_purchased' => 2500, 'status' => 'completed']);
        Payment::create(['user_id' => $u->id, 'kind' => 'points', 'amount' => 10, 'points_purchased' => 1000, 'status' => 'refunded', 'refunded_amount' => 10]);
        Payment::create(['user_id' => $u->id, 'kind' => 'flyer', 'amount' => 40, 'refunded_amount' => 15, 'status' => 'captured']);
        Payment::create(['user_id' => $u->id, 'kind' => 'flyer', 'amount' => 30, 'status' => 'authorized']);   // 보류 — 매출 아님

        $stats = $this->actingAs($s, 'api')->getJson('/api/admin/payments')->assertOk()->json('stats');
        $summary = $this->actingAs($s, 'api')->getJson('/api/admin/revenue/summary?range=all')->assertOk()->json('data');

        $this->assertEquals(50.0, $stats['totalRevenue']);            // 25 + (40-15)
        $this->assertEquals($summary['total']['net'], $stats['totalRevenue']);
        $this->assertEquals(50.0, $stats['monthRevenue']);
        $this->assertSame(2, $stats['totalRefunds']);                 // 전액 환불 1 + 부분 환불 1

        // 관리자 대시보드(종합 리포트)도 같은 숫자 — 통계 시작일 이후 기록만
        $report = $this->actingAs($s, 'api')->getJson('/api/admin/board-manager/full-report')->assertOk()->json();
        $pay = $report['data']['payments'] ?? $report['payments'];
        $this->assertEquals(50.0, $pay['total_revenue']);
        $this->assertEquals(50.0, $pay['today_revenue']);
    }

    public function test_direct_payment_refund_points_to_the_right_screen(): void
    {
        $s = $this->super();
        $p = Payment::create(['user_id' => User::factory()->create()->id, 'kind' => 'flyer', 'ref_id' => 7, 'amount' => 40, 'status' => 'captured', 'stripe_payment_id' => 'pi_x']);
        $res = $this->actingAs($s, 'api')->postJson("/api/admin/payments/{$p->id}/refund")->assertStatus(422);
        $this->assertStringContainsString('전단 관리', $res->json('message'));
        $this->assertSame('captured', $p->fresh()->status);
    }

    public function test_refund_takes_back_bonus_points_too(): void
    {
        $s = $this->super();
        $buyer = User::factory()->create(['points' => 0]);
        // $50 구매 + 15% 보너스 = 5750P 가 한 번에 지급되는 구조
        $p = Payment::create(['user_id' => $buyer->id, 'kind' => 'points', 'amount' => 50, 'points_purchased' => 5750, 'status' => 'completed']);
        $buyer->addPoints(5750, '포인트 구매 (5750P)');
        $this->actingAs($s, 'api')->postJson("/api/admin/payments/{$p->id}/refund")->assertOk();
        $this->assertSame(0, (int) $buyer->fresh()->points);
    }
}
