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
use App\Services\Cancellation\Strategies\DaycareCancellationStrategy;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Stripe\Exception\InvalidRequestException;
use Tests\TestCase;

class DaycareCancellationStrategyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance('current.tenant.id');
    }

    private function makePendingDaycareOrder(): array
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => 'acct_daycare']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);

        $order = Order::factory()->create([
            'tenant_id'   => $tenant->id,
            'customer_id' => $customer->id,
            'package_id'  => $package->id,
            'type'        => OrderType::Daycare,
            'status'      => 'pending',
        ]);

        return compact('tenant', 'order');
    }

    public function test_supports_returns_true_for_daycare_order_type(): void
    {
        $stripe   = $this->mock(StripeService::class)->shouldIgnoreMissing();
        $strategy = new DaycareCancellationStrategy($stripe);

        $order = Order::factory()->make(['type' => OrderType::Daycare]);

        $this->assertTrue($strategy->supports($order));
    }

    public function test_supports_returns_false_for_boarding_order_type(): void
    {
        $stripe   = $this->mock(StripeService::class)->shouldIgnoreMissing();
        $strategy = new DaycareCancellationStrategy($stripe);

        $order = Order::factory()->make(['type' => OrderType::Boarding]);

        $this->assertFalse($strategy->supports($order));
    }

    public function test_cancel_calls_stripe_and_marks_payment_and_order_canceled(): void
    {
        ['order' => $order] = $this->makePendingDaycareOrder();

        $payment = OrderPayment::factory()->forOrder($order)->create([
            'type'         => PaymentType::Full,
            'status'       => 'pending',
            'stripe_pi_id' => 'pi_daycare_full',
            'paid_at'      => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cancelPaymentIntent')
                ->once()
                ->with('pi_daycare_full', 'acct_daycare');
        });

        $strategy = new DaycareCancellationStrategy($stripe);
        $order->load(['payments', 'tenant']);
        $strategy->cancel($order);

        $this->assertEquals(PaymentStatus::Canceled, $payment->fresh()->status);
        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }

    public function test_cancel_marks_canceled_without_stripe_when_no_pi(): void
    {
        ['order' => $order] = $this->makePendingDaycareOrder();

        $payment = OrderPayment::factory()->forOrder($order)->pending()->create([
            'type' => PaymentType::Full,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        $strategy = new DaycareCancellationStrategy($stripe);
        $order->load(['payments', 'tenant']);
        $strategy->cancel($order);

        $this->assertEquals(PaymentStatus::Canceled, $payment->fresh()->status);
        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }

    public function test_cancel_marks_canceled_when_no_payment_exists(): void
    {
        ['order' => $order] = $this->makePendingDaycareOrder();

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        $strategy = new DaycareCancellationStrategy($stripe);
        $order->load(['payments', 'tenant']);
        $strategy->cancel($order);

        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }

    public function test_cancel_skips_stripe_when_no_stripe_account_id(): void
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => null]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);

        $order = Order::factory()->create([
            'tenant_id'   => $tenant->id,
            'customer_id' => $customer->id,
            'package_id'  => $package->id,
            'type'        => OrderType::Daycare,
            'status'      => 'pending',
        ]);

        $payment = OrderPayment::factory()->forOrder($order)->create([
            'type'         => PaymentType::Full,
            'status'       => 'pending',
            'stripe_pi_id' => 'pi_no_account',
            'paid_at'      => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        $strategy = new DaycareCancellationStrategy($stripe);
        $order->load(['payments', 'tenant']);
        $strategy->cancel($order);

        $this->assertEquals(PaymentStatus::Canceled, $payment->fresh()->status);
        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }

    public function test_cancel_logs_and_continues_when_stripe_throws(): void
    {
        ['order' => $order] = $this->makePendingDaycareOrder();

        $payment = OrderPayment::factory()->forOrder($order)->create([
            'type'         => PaymentType::Full,
            'status'       => 'pending',
            'stripe_pi_id' => 'pi_stripe_err',
            'paid_at'      => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cancelPaymentIntent')
                ->once()
                ->andThrow(new InvalidRequestException('Already canceled.', 400));
        });

        $strategy = new DaycareCancellationStrategy($stripe);
        $order->load(['payments', 'tenant']);
        $strategy->cancel($order);

        $this->assertEquals(PaymentStatus::Canceled, $payment->fresh()->status);
        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }
}
