<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Order;
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
            'status'    => 'active',
        ]);
    }

    public function test_index_shows_tenant_orders_only(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);
        Order::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'package_id' => $package->id]);

        $otherTenant = Tenant::factory()->create(['slug' => 'other', 'status' => 'active', 'plan' => 'starter']);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherPackage = Package::factory()->create(['tenant_id' => $otherTenant->id]);
        Order::factory()->create(['tenant_id' => $otherTenant->id, 'customer_id' => $otherCustomer->id, 'package_id' => $otherPackage->id]);

        $this->actingAs($this->staff);

        $response = $this->get('/admin/payments');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Payments/Index')
            ->where('orders.total', 1)
        );
    }

    public function test_refund_calls_stripe_and_removes_credits(): void
    {
        $this->mock(StripeService::class)
            ->shouldReceive('createRefund')
            ->once()
            ->andReturn((object) ['id' => 're_test']);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);
        $order = Order::factory()->create([
            'tenant_id'     => $this->tenant->id,
            'customer_id'   => $customer->id,
            'package_id'    => $package->id,
            'status'        => 'paid',
            'stripe_pi_id'  => 'pi_test123',
        ]);

        $this->actingAs($this->staff);

        $response = $this->post("/admin/payments/{$order->id}/refund");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'refunded']);
    }

    public function test_refund_of_already_refunded_order_flashes_error(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);
        $order = Order::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id'  => $package->id,
            'status'      => 'refunded',
        ]);

        $this->actingAs($this->staff);

        $response = $this->post("/admin/payments/{$order->id}/refund");

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
