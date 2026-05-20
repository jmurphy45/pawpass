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
use App\Services\Cancellation\Strategies\GroomingCancellationStrategy;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Stripe\Exception\InvalidRequestException;
use Tests\TestCase;

class GroomingCancellationStrategyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance('current.tenant.id');
    }

    private function makeGroomingOrder(string $status = 'pending'): array
    {
        $tenant = Tenant::factory()->create(['stripe_account_id' => 'acct_groom']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $tenant->id]);

        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'type' => OrderType::Grooming,
            'status' => $status,
        ]);

        return compact('tenant', 'order');
    }

    public function test_supports_returns_true_for_grooming_order(): void
    {
        $strategy = new GroomingCancellationStrategy($this->mock(StripeService::class)->shouldIgnoreMissing());

        $this->assertTrue($strategy->supports(Order::factory()->make(['type' => OrderType::Grooming])));
    }

    public function test_supports_returns_false_for_vet_order(): void
    {
        $strategy = new GroomingCancellationStrategy($this->mock(StripeService::class)->shouldIgnoreMissing());

        $this->assertFalse($strategy->supports(Order::factory()->make(['type' => OrderType::Vet])));
    }

    public function test_supports_returns_false_for_daycare_order(): void
    {
        $strategy = new GroomingCancellationStrategy($this->mock(StripeService::class)->shouldIgnoreMissing());

        $this->assertFalse($strategy->supports(Order::factory()->make(['type' => OrderType::Daycare])));
    }

    public function test_cancel_cancels_stripe_pi_and_marks_payment_canceled(): void
    {
        ['order' => $order] = $this->makeGroomingOrder('pending');

        $payment = OrderPayment::factory()->forOrder($order)->create([
            'type' => PaymentType::Deposit,
            'status' => 'pending',
            'stripe_pi_id' => 'pi_groom_pending',
            'paid_at' => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cancelPaymentIntent')
                ->once()
                ->with('pi_groom_pending', 'acct_groom');
        });

        $strategy = new GroomingCancellationStrategy($stripe);
        $order->load(['payments', 'tenant']);
        $strategy->cancel($order);

        $this->assertEquals(PaymentStatus::Canceled, $payment->fresh()->status);
        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }

    public function test_cancel_releases_authorized_payment_as_refunded(): void
    {
        ['order' => $order] = $this->makeGroomingOrder('authorized');

        $payment = OrderPayment::factory()->forOrder($order)->authorized()->create([
            'type' => PaymentType::Deposit,
            'paid_at' => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) use ($payment) {
            $mock->shouldReceive('cancelPaymentIntent')
                ->once()
                ->with($payment->stripe_pi_id, 'acct_groom');
        });

        $strategy = new GroomingCancellationStrategy($stripe);
        $order->load(['payments', 'tenant']);
        $strategy->cancel($order);

        $this->assertEquals(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }

    public function test_cancel_logs_and_continues_when_stripe_throws(): void
    {
        ['order' => $order] = $this->makeGroomingOrder('pending');

        $payment = OrderPayment::factory()->forOrder($order)->create([
            'type' => PaymentType::Deposit,
            'status' => 'pending',
            'stripe_pi_id' => 'pi_groom_err',
            'paid_at' => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cancelPaymentIntent')
                ->once()
                ->andThrow(new InvalidRequestException('Already canceled.', 400));
        });

        $strategy = new GroomingCancellationStrategy($stripe);
        $order->load(['payments', 'tenant']);
        $strategy->cancel($order);

        $this->assertEquals(PaymentStatus::Canceled, $payment->fresh()->status);
        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }

    public function test_cancel_transitions_order_with_no_payments(): void
    {
        ['order' => $order] = $this->makeGroomingOrder('pending');

        $strategy = new GroomingCancellationStrategy($this->mock(StripeService::class)->shouldIgnoreMissing());
        $order->load(['payments', 'tenant']);
        $strategy->cancel($order);

        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }
}
