<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class ManualPaymentControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->staff = User::factory()->staff()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'pending',
            'total_amount' => '100.00',
            'subtotal_cents' => 10000,
        ]);
    }

    private function auth(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];
    }

    public function test_record_cash_payment_creates_order_payment(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson("/api/admin/v1/orders/{$this->order->id}/payments", [
                'amount' => '100.00',
                'method' => 'cash',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $this->order->id,
            'amount_cents' => 10000,
            'method' => 'cash',
            'status' => 'paid',
        ]);
    }

    public function test_full_payment_transitions_order_to_paid(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/admin/v1/orders/{$this->order->id}/payments", [
                'amount' => '100.00',
                'method' => 'cash',
            ]);

        $this->order->refresh();
        $this->assertEquals(OrderStatus::Paid, $this->order->status);
    }

    public function test_partial_payment_does_not_change_status(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/admin/v1/orders/{$this->order->id}/payments", [
                'amount' => '50.00',
                'method' => 'check',
            ]);

        $this->order->refresh();
        $this->assertEquals(OrderStatus::Pending, $this->order->status);
    }

    public function test_cumulative_payments_transition_order_when_total_reached(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/admin/v1/orders/{$this->order->id}/payments", [
                'amount' => '50.00',
                'method' => 'cash',
            ]);

        $this->withHeaders($this->auth())
            ->postJson("/api/admin/v1/orders/{$this->order->id}/payments", [
                'amount' => '50.00',
                'method' => 'cash',
            ]);

        $this->order->refresh();
        $this->assertEquals(OrderStatus::Paid, $this->order->status);
    }

    public function test_overpayment_returns_422(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson("/api/admin/v1/orders/{$this->order->id}/payments", [
                'amount' => '150.00',
                'method' => 'cash',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['amount']);
    }

    public function test_invalid_method_returns_422(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson("/api/admin/v1/orders/{$this->order->id}/payments", [
                'amount' => '100.00',
                'method' => 'bitcoin',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['method']);
    }

    public function test_response_includes_updated_order_and_payments(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson("/api/admin/v1/orders/{$this->order->id}/payments", [
                'amount' => '100.00',
                'method' => 'cash',
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'order' => ['id', 'status', 'total_amount'],
                'payment' => ['id', 'amount_cents', 'method', 'paid_at'],
            ],
        ]);
    }

    public function test_wrong_tenant_cannot_record_payment(): void
    {
        $other = Tenant::factory()->create(['slug' => 'other', 'status' => 'active', 'plan' => 'starter']);
        $otherStaff = User::factory()->staff()->create(['tenant_id' => $other->id, 'status' => 'active']);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->jwtFor($otherStaff)])
            ->postJson("/api/admin/v1/orders/{$this->order->id}/payments", [
                'amount' => '100.00',
                'method' => 'cash',
            ]);

        $response->assertNotFound();
    }
}
