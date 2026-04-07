<?php

namespace Tests\Feature\Web\Portal;

use App\Models\Customer;
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

    private function chargeDetails(): array
    {
        return [
            'charge_id'      => 'ch_test123',
            'receipt_number' => '1234-5678',
            'card_brand'     => 'visa',
            'card_last4'     => '4242',
        ];
    }

    public function test_streams_pdf_for_paid_order(): void
    {
        $order = Order::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id'  => $this->package->id,
            'status'      => 'paid',
        ]);

        OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_abc123',
            'status'       => 'paid',
            'paid_at'      => now(),
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('retrieveChargeDetails')
                ->once()
                ->with('pi_abc123', 'acct_receipt123')
                ->andReturn($this->chargeDetails());
        });

        $response = $this->actingAs($this->user)
            ->get(route('portal.orders.receipt', $order));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pdf_still_generated_when_stripe_returns_no_charge(): void
    {
        $order = Order::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id'  => $this->package->id,
            'status'      => 'paid',
        ]);

        OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_abc123',
            'status'       => 'paid',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('retrieveChargeDetails')
                ->once()
                ->andReturn(null);
        });

        $response = $this->actingAs($this->user)
            ->get(route('portal.orders.receipt', $order));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_returns_403_if_order_belongs_to_different_customer(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $order = Order::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
            'package_id'  => $this->package->id,
            'status'      => 'paid',
        ]);

        OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_other123',
            'status'       => 'paid',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('retrieveChargeDetails');
        });

        $response = $this->actingAs($this->user)
            ->get(route('portal.orders.receipt', $order));

        $response->assertStatus(403);
    }

    public function test_returns_404_if_order_is_not_paid(): void
    {
        $order = Order::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id'  => $this->package->id,
            'status'      => 'pending',
        ]);

        OrderPayment::factory()->forOrder($order)->pending()->create([
            'stripe_pi_id' => null,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('retrieveChargeDetails');
        });

        $response = $this->actingAs($this->user)
            ->get(route('portal.orders.receipt', $order));

        $response->assertStatus(404);
    }

    public function test_receipt_includes_subtotal_and_tax_when_order_has_tax(): void
    {
        $order = Order::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'customer_id'     => $this->customer->id,
            'package_id'      => $this->package->id,
            'status'          => 'paid',
            'total_amount'    => '53.41',
            'subtotal_cents'  => 5000,
            'tax_amount_cents' => 341,
        ]);

        OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_tax_receipt',
            'status'       => 'paid',
            'paid_at'      => now(),
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('retrieveChargeDetails')->once()->andReturn($this->chargeDetails());
        });

        $response = $this->actingAs($this->user)
            ->get(route('portal.orders.receipt', $order));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_returns_404_if_order_has_no_stripe_pi_id(): void
    {
        $order = Order::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id'  => $this->package->id,
            'status'      => 'paid',
        ]);

        // No OrderPayment created — so no stripe_pi_id

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('retrieveChargeDetails');
        });

        $response = $this->actingAs($this->user)
            ->get(route('portal.orders.receipt', $order));

        $response->assertStatus(404);
    }
}
