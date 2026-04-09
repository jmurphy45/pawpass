<?php

namespace Tests\Feature\Jobs;

use App\Jobs\CancelStalePendingOrders;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Package;
use App\Models\Reservation;
use App\Models\Tenant;
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
            'tenant_id'   => $tenant->id,
            'customer_id' => $customer->id,
            'package_id'  => $package->id,
            'status'      => 'pending',
            'created_at'  => now()->subHours(2),
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

        (new CancelStalePendingOrders)->handle(app(StripeService::class));

        $this->assertEquals('canceled', $order->fresh()->status);
        $this->assertEquals('canceled', $payment->fresh()->status);
    }

    public function test_skips_orders_created_within_one_hour(): void
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => 'acct_test123']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);

        $order = Order::factory()->create([
            'tenant_id'   => $tenant->id,
            'customer_id' => $customer->id,
            'package_id'  => $package->id,
            'status'      => 'pending',
            'created_at'  => now()->subMinutes(30),
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        (new CancelStalePendingOrders)->handle(app(StripeService::class));

        $this->assertEquals('pending', $order->fresh()->status);
    }

    public function test_marks_canceled_when_no_stripe_pi_id_without_calling_stripe(): void
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => 'acct_test123']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);

        $order = Order::factory()->create([
            'tenant_id'   => $tenant->id,
            'customer_id' => $customer->id,
            'package_id'  => $package->id,
            'status'      => 'pending',
            'created_at'  => now()->subHours(2),
        ]);

        // pending() factory state sets stripe_pi_id to null
        OrderPayment::factory()->forOrder($order)->pending()->create();

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        (new CancelStalePendingOrders)->handle(app(StripeService::class));

        $this->assertEquals('canceled', $order->fresh()->status);
    }

    public function test_handles_stripe_api_error_gracefully_and_still_marks_canceled(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->makeStalePendingOrder();

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cancelPaymentIntent')
                ->once()
                ->andThrow(new InvalidRequestException('This PaymentIntent cannot be canceled because it has a status of succeeded.', 400));
        });

        (new CancelStalePendingOrders)->handle(app(StripeService::class));

        $this->assertEquals('canceled', $order->fresh()->status);
        $this->assertEquals('canceled', $payment->fresh()->status);
    }

    public function test_does_not_touch_non_pending_orders(): void
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => 'acct_test123']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);

        $paidOrder = Order::factory()->create([
            'tenant_id'   => $tenant->id,
            'customer_id' => $customer->id,
            'package_id'  => $package->id,
            'status'      => 'paid',
            'created_at'  => now()->subHours(2),
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        (new CancelStalePendingOrders)->handle(app(StripeService::class));

        $this->assertEquals('paid', $paidOrder->fresh()->status);
    }

    public function test_cancels_order_locally_when_tenant_has_no_stripe_account(): void
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => null]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);

        $order = Order::factory()->create([
            'tenant_id'   => $tenant->id,
            'customer_id' => $customer->id,
            'package_id'  => $package->id,
            'status'      => 'pending',
            'created_at'  => now()->subHours(2),
        ]);

        $payment = OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_no_account',
            'status'       => 'pending',
            'paid_at'      => null,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        (new CancelStalePendingOrders)->handle(app(StripeService::class));

        $this->assertEquals('canceled', $order->fresh()->status);
        $this->assertEquals('canceled', $payment->fresh()->status);
    }

    public function test_skips_orders_with_reservation_id(): void
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => 'acct_test123']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);

        $reservation = Reservation::factory()->create([
            'tenant_id' => $tenant->id,
            'status'    => 'pending',
        ]);

        $order = Order::factory()->create([
            'tenant_id'      => $tenant->id,
            'customer_id'    => $customer->id,
            'package_id'     => $package->id,
            'reservation_id' => $reservation->id,
            'status'         => 'pending',
            'created_at'     => now()->subHours(2),
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        (new CancelStalePendingOrders)->handle(app(StripeService::class));

        $this->assertEquals('pending', $order->fresh()->status);
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
            'tenant_id'   => $tenantA->id,
            'customer_id' => $customerA->id,
            'package_id'  => $packageA->id,
            'status'      => 'pending',
            'created_at'  => now()->subHours(2),
        ]);
        $orderB = Order::factory()->create([
            'tenant_id'   => $tenantB->id,
            'customer_id' => $customerB->id,
            'package_id'  => $packageB->id,
            'status'      => 'pending',
            'created_at'  => now()->subHours(2),
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

        (new CancelStalePendingOrders)->handle(app(StripeService::class));

        $this->assertEquals('canceled', $orderA->fresh()->status);
        $this->assertEquals('canceled', $orderB->fresh()->status);
    }
}
