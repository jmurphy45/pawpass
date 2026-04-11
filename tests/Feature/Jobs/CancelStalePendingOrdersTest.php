<?php

namespace Tests\Feature\Jobs;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Jobs\CancelStalePendingOrders;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Package;
use App\Models\Attendance;
use App\Models\Dog;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Cancellation\CancellationStrategyResolver;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Stripe\Exception\InvalidRequestException;
use Tests\TestCase;

class CancelStalePendingOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance('current.tenant.id');
    }

    private function makeStalePendingOrder(): array
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => 'acct_test123']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);

        $order = Order::factory()->create([
            'tenant_id'      => $tenant->id,
            'customer_id'    => $customer->id,
            'package_id'     => $package->id,
            'type'           => OrderType::Daycare,
            'status'         => 'pending',
            'cancellable_at' => now()->subMinutes(30),
        ]);

        $payment = OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_stale_test',
            'status'       => 'pending',
            'paid_at'      => null,
        ]);

        return compact('tenant', 'order', 'payment');
    }

    public function test_cancels_stale_pending_order_via_stripe_and_marks_canceled(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->makeStalePendingOrder();

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cancelPaymentIntent')
                ->once()
                ->with('pi_stale_test', 'acct_test123');
        });

        (new CancelStalePendingOrders)->handle(app(CancellationStrategyResolver::class));

        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
        $this->assertEquals(PaymentStatus::Canceled, $payment->fresh()->status);
    }

    public function test_skips_orders_with_future_cancellable_at(): void
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => 'acct_test123']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);

        $order = Order::factory()->create([
            'tenant_id'      => $tenant->id,
            'customer_id'    => $customer->id,
            'package_id'     => $package->id,
            'type'           => OrderType::Daycare,
            'status'         => 'pending',
            'cancellable_at' => now()->addMinutes(30),
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        (new CancelStalePendingOrders)->handle(app(CancellationStrategyResolver::class));

        $this->assertEquals(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_marks_canceled_when_no_stripe_pi_id_without_calling_stripe(): void
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => 'acct_test123']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);

        $order = Order::factory()->create([
            'tenant_id'      => $tenant->id,
            'customer_id'    => $customer->id,
            'package_id'     => $package->id,
            'type'           => OrderType::Daycare,
            'status'         => 'pending',
            'cancellable_at' => now()->subMinutes(30),
        ]);

        // pending() factory state sets stripe_pi_id to null
        OrderPayment::factory()->forOrder($order)->pending()->create();

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        (new CancelStalePendingOrders)->handle(app(CancellationStrategyResolver::class));

        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }

    public function test_handles_stripe_api_error_gracefully_and_still_marks_canceled(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->makeStalePendingOrder();

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cancelPaymentIntent')
                ->once()
                ->andThrow(new InvalidRequestException('This PaymentIntent cannot be canceled because it has a status of succeeded.', 400));
        });

        (new CancelStalePendingOrders)->handle(app(CancellationStrategyResolver::class));

        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
        $this->assertEquals(PaymentStatus::Canceled, $payment->fresh()->status);
    }

    public function test_does_not_touch_non_pending_orders(): void
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => 'acct_test123']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);

        $paidOrder = Order::factory()->create([
            'tenant_id'      => $tenant->id,
            'customer_id'    => $customer->id,
            'package_id'     => $package->id,
            'type'           => OrderType::Daycare,
            'status'         => 'paid',
            'cancellable_at' => now()->subMinutes(30),
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        (new CancelStalePendingOrders)->handle(app(CancellationStrategyResolver::class));

        $this->assertEquals(OrderStatus::Paid, $paidOrder->fresh()->status);
    }

    public function test_cancels_order_locally_when_tenant_has_no_stripe_account(): void
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => null]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);

        $order = Order::factory()->create([
            'tenant_id'      => $tenant->id,
            'customer_id'    => $customer->id,
            'package_id'     => $package->id,
            'type'           => OrderType::Daycare,
            'status'         => 'pending',
            'cancellable_at' => now()->subMinutes(30),
        ]);

        $payment = OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_no_account',
            'status'       => 'pending',
            'paid_at'      => null,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        (new CancelStalePendingOrders)->handle(app(CancellationStrategyResolver::class));

        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
        $this->assertEquals(PaymentStatus::Canceled, $payment->fresh()->status);
    }

    public function test_skips_boarding_order_when_reservation_has_not_ended(): void
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => 'acct_test123']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);

        $reservation = Reservation::factory()->create([
            'tenant_id' => $tenant->id,
            'status'    => 'confirmed',
            'ends_at'   => now()->addDays(2),
        ]);

        $order = Order::factory()->create([
            'tenant_id'      => $tenant->id,
            'customer_id'    => $customer->id,
            'package_id'     => $package->id,
            'type'           => OrderType::Boarding,
            'reservation_id' => $reservation->id,
            'status'         => 'pending',
            'cancellable_at' => now()->subMinutes(30),
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        (new CancelStalePendingOrders)->handle(app(CancellationStrategyResolver::class));

        $this->assertEquals(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_cancels_boarding_order_when_reservation_has_ended(): void
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => 'acct_boarding']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);

        $reservation = Reservation::factory()->create([
            'tenant_id' => $tenant->id,
            'status'    => 'checked_out',
            'starts_at' => now()->subDays(3),
            'ends_at'   => now()->subDay(),
        ]);

        $order = Order::factory()->create([
            'tenant_id'      => $tenant->id,
            'customer_id'    => $customer->id,
            'package_id'     => $package->id,
            'type'           => OrderType::Boarding,
            'reservation_id' => $reservation->id,
            'status'         => 'pending',
            'cancellable_at' => now()->subMinutes(30),
        ]);

        $payment = OrderPayment::factory()->forOrder($order)->create([
            'type'         => PaymentType::Deposit,
            'status'       => 'pending',
            'stripe_pi_id' => 'pi_boarding_stale',
            'paid_at'      => null,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cancelPaymentIntent')
                ->once()
                ->with('pi_boarding_stale', 'acct_boarding');
        });

        (new CancelStalePendingOrders)->handle(app(CancellationStrategyResolver::class));

        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
        $this->assertEquals(PaymentStatus::Canceled, $payment->fresh()->status);
    }

    public function test_cancels_stale_authorized_boarding_deposit_marks_payment_refunded(): void
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => 'acct_boarding']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);

        $order = Order::factory()->create([
            'tenant_id'      => $tenant->id,
            'customer_id'    => $customer->id,
            'package_id'     => $package->id,
            'type'           => OrderType::Boarding,
            'status'         => 'authorized',
            'cancellable_at' => now()->subMinutes(30),
        ]);

        $payment = OrderPayment::factory()->forOrder($order)->authorized()->create([
            'type'    => PaymentType::Deposit,
            'paid_at' => null,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cancelPaymentIntent')
                ->once()
                ->withArgs(fn ($pi, $account) => $account === 'acct_boarding');
        });

        (new CancelStalePendingOrders)->handle(app(CancellationStrategyResolver::class));

        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
        $this->assertEquals(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->refunded_at);
    }

    public function test_cancels_stale_pending_attendance_addon_order(): void
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => 'acct_test123']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);
        $staff    = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'staff']);
        $dog      = Dog::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);

        $attendance = Attendance::factory()->create([
            'tenant_id'     => $tenant->id,
            'dog_id'        => $dog->id,
            'checked_in_by' => $staff->id,
        ]);

        $order = Order::factory()->create([
            'tenant_id'      => $tenant->id,
            'customer_id'    => $customer->id,
            'package_id'     => $package->id,
            'type'           => OrderType::Daycare,
            'status'         => 'pending',
            'attendance_id'  => $attendance->id,
            'cancellable_at' => now()->subMinutes(30),
        ]);

        $payment = OrderPayment::factory()->forOrder($order)->create([
            'type'         => PaymentType::Charge,
            'status'       => 'pending',
            'stripe_pi_id' => 'pi_addon_stale',
            'paid_at'      => null,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cancelPaymentIntent')
                ->once()
                ->with('pi_addon_stale', 'acct_test123');
        });

        (new CancelStalePendingOrders)->handle(app(CancellationStrategyResolver::class));

        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
        $this->assertEquals(PaymentStatus::Canceled, $payment->fresh()->status);
    }

    public function test_cancels_stale_daycare_order_without_payment(): void
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => 'acct_test123']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);

        $order = Order::factory()->create([
            'tenant_id'      => $tenant->id,
            'customer_id'    => $customer->id,
            'package_id'     => $package->id,
            'type'           => OrderType::Daycare,
            'status'         => 'pending',
            'cancellable_at' => now()->subMinutes(30),
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        (new CancelStalePendingOrders)->handle(app(CancellationStrategyResolver::class));

        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }

    public function test_skips_orders_with_null_cancellable_at(): void
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => 'acct_test123']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);

        $order = Order::factory()->create([
            'tenant_id'      => $tenant->id,
            'customer_id'    => $customer->id,
            'package_id'     => $package->id,
            'type'           => OrderType::Daycare,
            'status'         => 'pending',
            'cancellable_at' => null,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        (new CancelStalePendingOrders)->handle(app(CancellationStrategyResolver::class));

        $this->assertEquals(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_processes_orders_across_all_tenants(): void
    {
        $tenantA = Tenant::factory()->create(['stripe_account_id' => 'acct_aaa']);
        $tenantB = Tenant::factory()->create(['stripe_account_id' => 'acct_bbb']);

        $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id]);
        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);
        $packageA  = Package::factory()->create(['tenant_id' => $tenantA->id]);
        $packageB  = Package::factory()->create(['tenant_id' => $tenantB->id]);

        $orderA = Order::factory()->create([
            'tenant_id'      => $tenantA->id,
            'customer_id'    => $customerA->id,
            'package_id'     => $packageA->id,
            'type'           => OrderType::Daycare,
            'status'         => 'pending',
            'cancellable_at' => now()->subMinutes(30),
        ]);
        $orderB = Order::factory()->create([
            'tenant_id'      => $tenantB->id,
            'customer_id'    => $customerB->id,
            'package_id'     => $packageB->id,
            'type'           => OrderType::Daycare,
            'status'         => 'pending',
            'cancellable_at' => now()->subMinutes(30),
        ]);

        OrderPayment::factory()->forOrder($orderA)->create([
            'stripe_pi_id' => 'pi_aaa',
            'status'       => 'pending',
            'paid_at'      => null,
        ]);
        OrderPayment::factory()->forOrder($orderB)->create([
            'stripe_pi_id' => 'pi_bbb',
            'status'       => 'pending',
            'paid_at'      => null,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cancelPaymentIntent')
                ->once()->with('pi_aaa', 'acct_aaa');
            $mock->shouldReceive('cancelPaymentIntent')
                ->once()->with('pi_bbb', 'acct_bbb');
        });

        (new CancelStalePendingOrders)->handle(app(CancellationStrategyResolver::class));

        $this->assertEquals(OrderStatus::Canceled, $orderA->fresh()->status);
        $this->assertEquals(OrderStatus::Canceled, $orderB->fresh()->status);
    }
}
