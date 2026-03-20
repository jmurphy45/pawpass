<?php

namespace Tests\Feature\Web\Portal;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Tests\TestCase;

class OrderReceiptControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Customer $customer;
    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'slug'              => 'receipttest',
            'status'            => 'active',
            'plan'              => 'starter',
            'stripe_account_id' => 'acct_receipt123',
        ]);
        URL::forceRootUrl('http://receipttest.pawpass.com');

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->user = User::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'role'        => 'customer',
        ]);

        $this->package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_authenticated_customer_is_redirected_to_receipt_url(): void
    {
        $order = Order::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'customer_id'  => $this->customer->id,
            'package_id'   => $this->package->id,
            'status'       => 'paid',
            'stripe_pi_id' => 'pi_abc123',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('retrieveReceiptUrl')
                ->once()
                ->with('pi_abc123', 'acct_receipt123')
                ->andReturn('https://pay.stripe.com/receipts/test_receipt_url');
        });

        $response = $this->actingAs($this->user)
            ->get(route('portal.orders.receipt', $order));

        $response->assertRedirect('https://pay.stripe.com/receipts/test_receipt_url');
    }

    public function test_returns_403_if_order_belongs_to_different_customer(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $order = Order::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'customer_id'  => $otherCustomer->id,
            'package_id'   => $this->package->id,
            'status'       => 'paid',
            'stripe_pi_id' => 'pi_other123',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('retrieveReceiptUrl');
        });

        $response = $this->actingAs($this->user)
            ->get(route('portal.orders.receipt', $order));

        $response->assertStatus(403);
    }

    public function test_returns_404_if_order_is_not_paid(): void
    {
        $order = Order::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'customer_id'  => $this->customer->id,
            'package_id'   => $this->package->id,
            'status'       => 'pending',
            'stripe_pi_id' => 'pi_abc123',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('retrieveReceiptUrl');
        });

        $response = $this->actingAs($this->user)
            ->get(route('portal.orders.receipt', $order));

        $response->assertStatus(404);
    }

    public function test_returns_404_if_order_has_no_stripe_pi_id(): void
    {
        $order = Order::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'customer_id'  => $this->customer->id,
            'package_id'   => $this->package->id,
            'status'       => 'paid',
            'stripe_pi_id' => null,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('retrieveReceiptUrl');
        });

        $response = $this->actingAs($this->user)
            ->get(route('portal.orders.receipt', $order));

        $response->assertStatus(404);
    }

    public function test_returns_404_if_stripe_returns_no_receipt_url(): void
    {
        $order = Order::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'customer_id'  => $this->customer->id,
            'package_id'   => $this->package->id,
            'status'       => 'paid',
            'stripe_pi_id' => 'pi_abc123',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('retrieveReceiptUrl')
                ->once()
                ->andReturn(null);
        });

        $response = $this->actingAs($this->user)
            ->get(route('portal.orders.receipt', $order));

        $response->assertStatus(404);
    }
}
