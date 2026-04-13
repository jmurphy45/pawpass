<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class PaymentControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Customer $customer;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create([
            'slug' => 'paytest',
            'status' => 'active',
            'stripe_account_id' => 'acct_pay123',
        ]);
        URL::forceRootUrl('http://paytest.pawpass.com');

        $this->staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'one_time',
            'credit_count' => 10,
        ]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];
    }

    public function test_index_returns_tenant_orders_with_customer_and_package(): void
    {
        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'status' => 'paid',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/payments');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'status', 'total_amount', 'package', 'customer']]]);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_status_filter_returns_only_matching(): void
    {
        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'status' => 'paid',
        ]);

        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'status' => 'refunded',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/payments?status=paid');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('paid', $response->json('data.0.status'));
    }

    public function test_refund_marks_order_refunded_and_removes_credits(): void
    {
        $dog = Dog::factory()->forCustomer($this->customer)->withCredits(10)->create();

        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'status' => 'paid',
        ]);

        OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_refund123',
            'status'       => 'paid',
        ]);

        $order->orderDogs()->create(['dog_id' => $dog->id, 'credits_issued' => 10]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createRefund')
                ->once()
                ->with('pi_refund123', 'acct_pay123')
                ->andReturn((object) ['id' => 're_test123']);
        });

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/admin/v1/payments/{$order->id}/refund");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'refunded');

        $order->refresh();
        $this->assertEquals(OrderStatus::Refunded, $order->status);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'status' => 'refunded']);

        $dog->refresh();
        $this->assertEquals(0, $dog->credit_balance);

        $this->assertDatabaseHas('credit_ledger', [
            'dog_id' => $dog->id,
            'type' => 'refund',
        ]);
    }

    public function test_refund_already_refunded_order_returns_409(): void
    {
        $order = Order::factory()->refunded()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/admin/v1/payments/{$order->id}/refund");

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'ORDER_NOT_REFUNDABLE');
    }

    public function test_refund_order_from_other_tenant_returns_404(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'otherpay', 'status' => 'active']);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherPackage = Package::factory()->create(['tenant_id' => $otherTenant->id]);

        $otherOrder = Order::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'package_id' => $otherPackage->id,
            'status' => 'paid',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/admin/v1/payments/{$otherOrder->id}/refund");

        $response->assertStatus(404);
    }

    public function test_refund_returns_502_when_stripe_api_fails(): void
    {
        $dog = Dog::factory()->forCustomer($this->customer)->withCredits(5)->create();

        $order = Order::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'status'     => 'paid',
        ]);

        OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_refund_fail',
            'status'       => 'paid',
        ]);

        $order->orderDogs()->create(['dog_id' => $dog->id, 'credits_issued' => 5]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createRefund')
                ->once()
                ->andThrow(new \Stripe\Exception\ApiConnectionException('Stripe unreachable'));
        });

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/admin/v1/payments/{$order->id}/refund")
            ->assertStatus(502)
            ->assertJsonPath('message', 'Stripe unreachable');

        // Order and credits must NOT have been modified
        $this->assertEquals(OrderStatus::Paid, $order->fresh()->status);
        $this->assertEquals(5, $dog->fresh()->credit_balance);
    }
}
