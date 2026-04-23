<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);
    }

    public function test_index_shows_tenant_payments_only(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);
        $order = Order::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'package_id' => $package->id]);
        OrderPayment::factory()->forOrder($order)->create();

        $otherTenant = Tenant::factory()->create(['slug' => 'other', 'status' => 'active', 'plan' => 'starter']);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherPackage = Package::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherOrder = Order::factory()->create(['tenant_id' => $otherTenant->id, 'customer_id' => $otherCustomer->id, 'package_id' => $otherPackage->id]);
        OrderPayment::factory()->forOrder($otherOrder)->create();

        $this->actingAs($this->staff);

        $response = $this->get('/admin/payments');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Payments/Index')
            ->where('payments.total', 1)
        );
    }

    public function test_refund_calls_stripe_and_removes_credits(): void
    {
        $this->mock(StripeService::class)
            ->shouldReceive('createRefund')
            ->once()
            ->with('pi_test123', null, null)
            ->andReturn((object) ['id' => 're_test']);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'paid',
        ]);

        OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_test123',
            'status' => 'paid',
        ]);

        $this->actingAs($this->staff);

        $response = $this->post("/admin/payments/{$order->id}/refund", ['refund_type' => 'full']);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'refunded']);
    }

    public function test_refund_of_already_refunded_order_flashes_error(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'refunded',
        ]);

        $this->actingAs($this->staff);

        $response = $this->post("/admin/payments/{$order->id}/refund", ['refund_type' => 'full']);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_refund_passes_stripe_account_id_to_create_refund(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'paid',
        ]);

        OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_acct_test',
            'status' => 'paid',
        ]);

        // Set stripe_account_id AFTER package creation so SyncPackageToStripe skipped it
        $this->tenant->update(['stripe_account_id' => 'acct_webpay123']);

        $this->mock(StripeService::class)
            ->shouldReceive('createRefund')
            ->once()
            ->with('pi_acct_test', 'acct_webpay123', null)
            ->andReturn((object) ['id' => 're_acct']);

        $this->actingAs($this->staff);

        $response = $this->post("/admin/payments/{$order->id}/refund", ['refund_type' => 'full']);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_refund_already_refunded_order_returns_error_without_calling_stripe(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'refunded',
        ]);

        OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_already_refunded',
            'status' => 'refunded',
            'refunded_at' => now(),
        ]);

        $this->mock(StripeService::class)
            ->shouldReceive('createRefund')
            ->never();

        $this->actingAs($this->staff);

        $response = $this->post("/admin/payments/{$order->id}/refund", ['refund_type' => 'full']);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
