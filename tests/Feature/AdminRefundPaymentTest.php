<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Support\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 관리자 환불 버튼: Stripe 카드 환불 + 포인트 회수 + 주문 상태가 한 덩어리로 움직이는지.
 * 마이그레이션이 MySQL/MariaDB 전용이라 sqlite(phpunit.xml 기본값)가 아니라 MariaDB 테스트 DB 로 실행:
 *   DB_CONNECTION=mysql DB_DATABASE=<테스트DB> DB_USERNAME=.. DB_PASSWORD=.. php artisan test tests/Feature/AdminRefundPaymentTest.php
 */
class AdminRefundPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGateway(string $mode): StripeGateway
    {
        $fake = new class($mode) extends StripeGateway {
            public array $calls = [];
            public function __construct(private string $mode) {}
            public function refundFull(string $id, string $key): array
            {
                $this->calls[] = [$id, $key];
                return match ($this->mode) {
                    'ok' => ['result' => 'refunded', 'cents' => 2500],
                    'already' => ['result' => 'already_refunded', 'cents' => 0],
                    'not_succeeded' => throw new \DomainException('Stripe 에서 결제가 완료된 상태가 아니라 카드 환불을 할 수 없어요'),
                    default => throw new \Stripe\Exception\UnknownApiErrorException('boom'),
                };
            }
        };
        $this->app->instance(StripeGateway::class, $fake);
        return $fake;
    }

    private function makeOrder(?string $intent = 'pi_test_1'): array
    {
        // 환불은 최고관리자 전용 (AdminTier 등급표)
        $admin = User::factory()->create(['role' => 'super_admin']);
        $buyer = User::factory()->create(['points' => 3000]);
        $payment = Payment::create(['user_id' => $buyer->id, 'stripe_payment_id' => $intent, 'amount' => 25, 'points_purchased' => 2500, 'status' => 'completed']);
        return [$admin, $buyer, $payment];
    }

    public function test_refund_charges_back_the_card_and_takes_points_back(): void
    {
        [$admin, $buyer, $payment] = $this->makeOrder();
        $fake = $this->fakeGateway('ok');
        $this->actingAs($admin, 'api')->postJson("/api/admin/payments/{$payment->id}/refund")
            ->assertOk()->assertJsonPath('success', true);
        $this->assertSame([['pi_test_1', "refund-payment-{$payment->id}"]], $fake->calls);
        $payment->refresh();
        $this->assertSame('refunded', $payment->status);
        $this->assertSame('25.00', (string) $payment->refunded_amount);
        $this->assertSame(500, $buyer->fresh()->points);
    }

    public function test_stripe_failure_leaves_everything_untouched(): void
    {
        [$admin, $buyer, $payment] = $this->makeOrder();
        $this->fakeGateway('error');
        $this->actingAs($admin, 'api')->postJson("/api/admin/payments/{$payment->id}/refund")->assertStatus(502);
        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame(3000, $buyer->fresh()->points);
    }

    public function test_card_not_charged_on_stripe_blocks_the_refund(): void
    {
        [$admin, $buyer, $payment] = $this->makeOrder();
        $this->fakeGateway('not_succeeded');
        $this->actingAs($admin, 'api')->postJson("/api/admin/payments/{$payment->id}/refund")->assertStatus(422);
        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame(3000, $buyer->fresh()->points);
    }

    public function test_already_refunded_on_stripe_only_fixes_points_and_status(): void
    {
        [$admin, $buyer, $payment] = $this->makeOrder();
        $this->fakeGateway('already');
        $this->actingAs($admin, 'api')->postJson("/api/admin/payments/{$payment->id}/refund")->assertOk();
        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame(500, $buyer->fresh()->points);
    }

    public function test_order_without_stripe_id_skips_card_refund(): void
    {
        [$admin, $buyer, $payment] = $this->makeOrder(null);
        $fake = $this->fakeGateway('ok');
        $this->actingAs($admin, 'api')->postJson("/api/admin/payments/{$payment->id}/refund")->assertOk();
        $this->assertSame([], $fake->calls);
        $this->assertSame('refunded', $payment->fresh()->status);
    }

    public function test_cannot_refund_twice_and_moderators_are_rejected(): void
    {
        [$admin, $buyer, $payment] = $this->makeOrder();
        $fake = $this->fakeGateway('ok');
        $mod = User::factory()->create(['role' => 'moderator']);
        $this->actingAs($mod, 'api')->postJson("/api/admin/payments/{$payment->id}/refund")->assertStatus(403);
        $plainAdmin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($plainAdmin, 'api')->postJson("/api/admin/payments/{$payment->id}/refund")->assertStatus(403);
        $this->actingAs($admin, 'api')->postJson("/api/admin/payments/{$payment->id}/refund")->assertOk();
        $this->actingAs($admin, 'api')->postJson("/api/admin/payments/{$payment->id}/refund")->assertStatus(422);
        $this->assertCount(1, $fake->calls);
        $this->assertSame(500, $buyer->fresh()->points);
    }
}
