<?php

namespace Tests\Unit\Services\Cancellation;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Package;
use App\Models\Tenant;
use App\Services\Cancellation\Strategies\BoardingCancellationStrategy;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Stripe\Exception\InvalidRequestException;
use Tests\TestCase;

class BoardingCancellationStrategyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance('current.tenant.id');
    }

    private function makeBoardingOrder(string $status = 'pending'): array
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => 'acct_boarding']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);

        $order = Order::factory()->create([
            'tenant_id'   => $tenant->id,
            'customer_id' => $customer->id,
            'package_id'  => $package->id,
            'type'        => OrderType::Boarding,
            'status'      => $status,
        ]);

        return compact('tenant', 'order');
    }

    public function test_supports_returns_true_for_boarding_order_type(): void
    {
        $stripe   = $this->mock(StripeService::class)->shouldIgnoreMissing();
        $strategy = new BoardingCancellationStrategy($stripe);

        $order = Order::factory()->make(['type' => OrderType::Boarding]);

        $this->assertTrue($strategy->supports($order));
    }

    public function test_supports_returns_false_for_daycare_order_type(): void
    {
        $stripe   = $this->mock(StripeService::class)->shouldIgnoreMissing();
        $strategy = new BoardingCancellationStrategy($stripe);

        $order = Order::factory()->make(['type' => OrderType::Daycare]);

        $this->assertFalse($strategy->supports($order));
    }

    public function test_cancel_releases_authorized_deposit_hold_via_stripe_and_marks_payment_refunded(): void
    {
        ['tenant' => $tenant, 'order' => $order] = $this->makeBoardingOrder('authorized');

        $payment = OrderPayment::factory()->forOrder($order)->authorized()->create([
            'type'    => PaymentType::Deposit,
            'paid_at' => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) use ($payment) {
            $mock->shouldReceive('cancelPaymentIntent')
                ->once()
                ->with($payment->stripe_pi_id, 'acct_boarding');
        });

        $strategy = new BoardingCancellationStrategy($stripe);
        $order->load(['payments', 'tenant']);
        $strategy->cancel($order);

        $this->assertEquals(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->refunded_at);
        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }

    public function test_cancel_cancels_pending_deposit_pi_and_marks_payment_canceled(): void
    {
        ['order' => $order] = $this->makeBoardingOrder('pending');

        $payment = OrderPayment::factory()->forOrder($order)->create([
            'type'         => PaymentType::Deposit,
            'status'       => 'pending',
            'stripe_pi_id' => 'pi_boarding_pending',
            'paid_at'      => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cancelPaymentIntent')
                ->once()
                ->with('pi_boarding_pending', 'acct_boarding');
        });

        $strategy = new BoardingCancellationStrategy($stripe);
        $order->load(['payments', 'tenant']);
        $strategy->cancel($order);

        $this->assertEquals(PaymentStatus::Canceled, $payment->fresh()->status);
        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }

    public function test_cancel_skips_stripe_when_no_stripe_account_id(): void
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => null]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);
        $order    = Order::factory()->create([
            'tenant_id'   => $tenant->id,
            'customer_id' => $customer->id,
            'package_id'  => $package->id,
            'type'        => OrderType::Boarding,
            'status'      => 'pending',
        ]);

        OrderPayment::factory()->forOrder($order)->create([
            'type'         => PaymentType::Deposit,
            'status'       => 'pending',
            'stripe_pi_id' => 'pi_no_account',
            'paid_at'      => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        $strategy = new BoardingCancellationStrategy($stripe);
        $order->load(['payments', 'tenant']);
        $strategy->cancel($order);

        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }

    public function test_cancel_logs_and_continues_when_stripe_throws(): void
    {
        ['order' => $order] = $this->makeBoardingOrder('pending');

        $payment = OrderPayment::factory()->forOrder($order)->create([
            'type'         => PaymentType::Deposit,
            'status'       => 'pending',
            'stripe_pi_id' => 'pi_stripe_err',
            'paid_at'      => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cancelPaymentIntent')
                ->once()
                ->andThrow(new InvalidRequestException('Already canceled.', 400));
        });

        $strategy = new BoardingCancellationStrategy($stripe);
        $order->load(['payments', 'tenant']);
        $strategy->cancel($order);

        $this->assertEquals(PaymentStatus::Canceled, $payment->fresh()->status);
        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }

    public function test_cancel_transitions_order_to_canceled_with_no_payments(): void
    {
        ['order' => $order] = $this->makeBoardingOrder('pending');

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        $strategy = new BoardingCancellationStrategy($stripe);
        $order->load(['payments', 'tenant']);
        $strategy->cancel($order);

        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }
}
