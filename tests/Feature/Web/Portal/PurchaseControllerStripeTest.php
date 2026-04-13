<?php

namespace Tests\Feature\Web\Portal;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DogCreditService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Laravel\Pennant\Feature;
use Mockery\MockInterface;
use Tests\TestCase;

class PurchaseControllerStripeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private Dog $dog;

    protected function tearDown(): void
    {
        Feature::flushCache();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'slug'              => 'purchasetest',
            'status'            => 'active',
            'plan'              => 'starter',
            'stripe_account_id' => 'acct_purchase123',
            'platform_fee_pct'  => '5.00',
        ]);
        URL::forceRootUrl('http://purchasetest.pawpass.com');

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Jane Buyer',
            'email'     => 'jane@example.com',
        ]);
        $this->user = User::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'role'        => 'customer',
        ]);
        $this->customer->update(['user_id' => $this->user->id]);
        $this->dog = Dog::factory()->forCustomer($this->customer)->create();
    }

    public function test_store_creates_customer_on_connected_account_when_none_exists(): void
    {
        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type'      => 'one_time',
            'price'     => '50.00',
            'is_active' => true,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')
                ->once()
                ->with('jane@example.com', 'Jane Buyer', 'acct_purchase123')
                ->andReturn((object) ['id' => 'cus_new_conn']);

            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->with(
                    5000,
                    'usd',
                    'acct_purchase123',
                    \Mockery::any(),
                    \Mockery::any(),
                    'cus_new_conn',
                    \Mockery::any(),
                    \Mockery::any(),
                    \Mockery::any(),
                    \Mockery::any(),
                    \Mockery::any(),
                )
                ->andReturn((object) ['id' => 'pi_test', 'client_secret' => 'pi_secret']);
        });

        $this->actingAs($this->user);

        $response = $this->postJson('/my/purchase', [
            'package_id' => $package->id,
            'dog_ids'    => [$this->dog->id],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['client_secret']);

        $this->assertDatabaseHas('customers', [
            'id'                 => $this->customer->id,
            'stripe_customer_id' => 'cus_new_conn',
        ]);
    }

    public function test_store_reuses_existing_stripe_customer_id(): void
    {
        $this->customer->update(['stripe_customer_id' => 'cus_existing_conn']);

        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type'      => 'one_time',
            'price'     => '50.00',
            'is_active' => true,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('createCustomer');

            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->with(
                    \Mockery::any(),
                    'usd',
                    'acct_purchase123',
                    \Mockery::any(),
                    \Mockery::any(),
                    'cus_existing_conn',
                    \Mockery::any(),
                    \Mockery::any(),
                    \Mockery::any(),
                    \Mockery::any(),
                    \Mockery::any(),
                )
                ->andReturn((object) ['id' => 'pi_reuse', 'client_secret' => 'pi_reuse_secret']);
        });

        $this->actingAs($this->user);

        $response = $this->postJson('/my/purchase', [
            'package_id' => $package->id,
            'dog_ids'    => [$this->dog->id],
        ]);

        $response->assertStatus(200);
    }

    public function test_store_payment_intent_has_no_transfer_data(): void
    {
        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type'      => 'one_time',
            'price'     => '100.00',
            'is_active' => true,
        ]);

        $capturedPayload = null;

        $this->mock(StripeService::class, function (MockInterface $mock) use (&$capturedPayload) {
            $mock->shouldReceive('createCustomer')
                ->andReturn((object) ['id' => 'cus_nodest']);

            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturnUsing(function () use (&$capturedPayload) {
                    $capturedPayload = func_get_args();
                    return (object) ['id' => 'pi_nodest', 'client_secret' => 'secret'];
                });
        });

        $this->actingAs($this->user);

        $this->postJson('/my/purchase', [
            'package_id' => $package->id,
            'dog_ids'    => [$this->dog->id],
        ]);

        // 3rd positional arg is stripeAccountId (not transferDestination)
        $this->assertEquals('acct_purchase123', $capturedPayload[2]);
    }

    public function test_store_multi_dog_creates_order_with_multiple_order_dogs(): void
    {
        $dog2 = Dog::factory()->forCustomer($this->customer)->create();

        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type'      => 'one_time',
            'price'     => '99.00',
            'dog_limit' => 2,
            'is_active' => true,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')->andReturn((object) ['id' => 'cus_multi']);
            $mock->shouldReceive('createPaymentIntent')->once()->andReturn((object) ['id' => 'pi_multi', 'client_secret' => 'secret_multi']);
        });

        $this->actingAs($this->user);

        $response = $this->postJson('/my/purchase', [
            'package_id' => $package->id,
            'dog_ids'    => [$this->dog->id, $dog2->id],
        ]);

        $response->assertStatus(200);

        $order = Order::where('package_id', $package->id)->first();
        $this->assertNotNull($order);
        $this->assertCount(2, $order->orderDogs);
    }

    public function test_store_rejects_dog_ids_exceeding_dog_limit(): void
    {
        $dog2 = Dog::factory()->forCustomer($this->customer)->create();

        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type'      => 'one_time',
            'price'     => '49.00',
            'dog_limit' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson('/my/purchase', [
            'package_id' => $package->id,
            'dog_ids'    => [$this->dog->id, $dog2->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['dog_ids']);
    }

    public function test_store_rejects_empty_dog_ids(): void
    {
        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type'      => 'one_time',
            'is_active' => true,
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson('/my/purchase', [
            'package_id' => $package->id,
            'dog_ids'    => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['dog_ids']);
    }

    public function test_store_calculates_tax_and_adds_to_payment_intent_when_flag_active_and_tenant_has_address(): void
    {
        $this->tenant->update([
            'tax_collection_enabled' => true,
            'billing_address'        => ['postal_code' => '10001', 'country' => 'US'],
        ]);

        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type'      => 'one_time',
            'price'     => '50.00',
            'is_active' => true,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) use ($package) {
            $mock->shouldReceive('createCustomer')
                ->andReturn((object) ['id' => 'cus_tax']);

            $mock->shouldReceive('calculateTax')
                ->once()
                ->with(5000, 'usd', 'acct_purchase123', ['postal_code' => '10001', 'country' => 'US'], $package->id)
                ->andReturn((object) ['id' => 'taxcalc_123', 'tax_amount_exclusive' => 441]);

            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->withArgs(function (int $amountCents): bool {
                    return $amountCents === 5441; // 5000 + 441 tax
                })
                ->andReturn((object) ['id' => 'pi_tax', 'client_secret' => 'secret_tax']);
        });

        $this->actingAs($this->user);

        $response = $this->postJson('/my/purchase', [
            'package_id' => $package->id,
            'dog_ids'    => [$this->dog->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['tax_amount_cents' => 441]);

        $order = Order::where('customer_id', $this->customer->id)->latest()->first();
        $this->assertSame(441, $order->tax_amount_cents);
        $this->assertSame('taxcalc_123', $order->stripe_tax_calc_id);
    }

    public function test_store_skips_tax_when_flag_inactive(): void
    {
        Feature::define('tax_daycare_orders', fn () => false);

        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type'      => 'one_time',
            'price'     => '50.00',
            'is_active' => true,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')
                ->andReturn((object) ['id' => 'cus_notax']);

            $mock->shouldNotReceive('calculateTax');

            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->withArgs(function (int $amountCents): bool {
                    return $amountCents === 5000;
                })
                ->andReturn((object) ['id' => 'pi_notax', 'client_secret' => 'secret_notax']);
        });

        $this->actingAs($this->user);

        $this->postJson('/my/purchase', [
            'package_id' => $package->id,
            'dog_ids'    => [$this->dog->id],
        ])->assertStatus(200)->assertJsonFragment(['tax_amount_cents' => 0]);
    }

    public function test_store_skips_tax_when_tenant_has_no_billing_address(): void
    {
        Feature::define('tax_daycare_orders', fn () => true);
        // tenant has no billing_address set (default from setUp)

        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type'      => 'one_time',
            'price'     => '50.00',
            'is_active' => true,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')
                ->andReturn((object) ['id' => 'cus_noaddr']);

            $mock->shouldNotReceive('calculateTax');

            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->withArgs(function (int $amountCents): bool {
                    return $amountCents === 5000;
                })
                ->andReturn((object) ['id' => 'pi_noaddr', 'client_secret' => 'secret_noaddr']);
        });

        $this->actingAs($this->user);

        $this->postJson('/my/purchase', [
            'package_id' => $package->id,
            'dog_ids'    => [$this->dog->id],
        ])->assertStatus(200)->assertJsonFragment(['tax_amount_cents' => 0]);
    }

    public function test_tax_preview_returns_amounts_when_flag_active_and_tenant_has_address(): void
    {
        $this->tenant->update([
            'tax_collection_enabled' => true,
            'billing_address'        => ['postal_code' => '90210', 'country' => 'US'],
        ]);

        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type'      => 'one_time',
            'price'     => '45.00',
            'is_active' => true,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) use ($package) {
            $mock->shouldReceive('calculateTax')
                ->once()
                ->with(4500, 'usd', 'acct_purchase123', ['postal_code' => '90210', 'country' => 'US'], $package->id)
                ->andReturn((object) ['id' => 'txc_preview', 'tax_amount_exclusive' => 396]);
        });

        $this->actingAs($this->user);

        $response = $this->getJson('/my/purchase/tax-preview?package_id=' . $package->id);

        $response->assertStatus(200)->assertJson([
            'tax_enabled'    => true,
            'subtotal_cents' => 4500,
            'tax_cents'      => 396,
            'total_cents'    => 4896,
        ]);
    }

    public function test_tax_preview_returns_tax_disabled_when_flag_inactive(): void
    {
        Feature::define('tax_daycare_orders', fn () => false);

        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type'      => 'one_time',
            'price'     => '45.00',
            'is_active' => true,
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/my/purchase/tax-preview?package_id=' . $package->id);

        $response->assertStatus(200)->assertJson(['tax_enabled' => false]);
    }

    public function test_tax_preview_returns_tax_disabled_when_tenant_has_no_billing_address(): void
    {
        Feature::define('tax_daycare_orders', fn () => true);
        // tenant has no billing_address

        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type'      => 'one_time',
            'price'     => '45.00',
            'is_active' => true,
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/my/purchase/tax-preview?package_id=' . $package->id);

        $response->assertStatus(200)->assertJson(['tax_enabled' => false]);
    }

    public function test_store_sets_subtotal_cents_on_order(): void
    {
        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type'      => 'one_time',
            'price'     => '75.00',
            'is_active' => true,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')->andReturn((object) ['id' => 'cus_sub']);
            $mock->shouldReceive('createPaymentIntent')->andReturn((object) ['id' => 'pi_sub', 'client_secret' => 'secret_sub']);
        });

        $this->actingAs($this->user);

        $this->postJson('/my/purchase', [
            'package_id' => $package->id,
            'dog_ids'    => [$this->dog->id],
        ])->assertStatus(200);

        $order = Order::where('package_id', $package->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertSame(7500, $order->subtotal_cents);
    }

    public function test_store_payment_intent_restricts_to_card_and_bank_payment_methods(): void
    {
        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type'      => 'one_time',
            'price'     => '50.00',
            'is_active' => true,
        ]);

        $capturedTypes = null;

        $this->mock(StripeService::class, function (MockInterface $mock) use (&$capturedTypes) {
            $mock->shouldReceive('createCustomer')->andReturn((object) ['id' => 'cus_pm']);
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturnUsing(function () use (&$capturedTypes) {
                    $args = func_get_args();
                    // paymentMethodTypes is the 10th argument (index 9, named param)
                    $capturedTypes = $args[9] ?? null;
                    return (object) ['id' => 'pi_pm', 'client_secret' => 'secret_pm'];
                });
        });

        $this->actingAs($this->user);

        $this->postJson('/my/purchase', [
            'package_id' => $package->id,
            'dog_ids'    => [$this->dog->id],
        ])->assertStatus(200);

        $this->assertEquals(['card', 'us_bank_account'], $capturedTypes);
    }

    public function test_confirm_issues_unlimited_credits_for_unlimited_package(): void
    {
        $package = Package::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'type'       => 'unlimited',
            'price'      => '199.00',
            'is_active'  => true,
            'dog_limit'  => 1,
        ]);

        $this->dog->update(['credit_balance' => 0]);

        $order = Order::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id'  => $package->id,
            'status'      => 'pending',
        ]);
        $order->orderDogs()->create(['dog_id' => $this->dog->id, 'credits_issued' => 0]);
        OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_unlimited_test',
            'status'       => 'pending',
            'type'         => 'full',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('retrievePaymentIntent')
                ->once()
                ->andReturn((object) ['status' => 'succeeded']);
        });

        $this->actingAs($this->user);

        $response = $this->postJson('/my/purchase/confirm', [
            'payment_intent_id' => 'pi_unlimited_test',
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'paid']);

        $fresh = $this->dog->fresh();
        $this->assertSame(now()->daysInMonth, $fresh->credit_balance);
        $this->assertNull($fresh->credits_expire_at);
        $this->assertNotNull($fresh->unlimited_pass_expires_at);
    }

    public function test_confirm_returns_non_200_when_credit_service_fails(): void
    {
        $package = Package::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'type'       => 'one_time',
            'price'      => '50.00',
            'credit_count' => 5,
            'is_active'  => true,
            'dog_limit'  => 1,
        ]);

        $this->dog->update(['credit_balance' => 0]);

        $order = Order::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id'  => $package->id,
            'status'      => 'pending',
        ]);
        $order->orderDogs()->create(['dog_id' => $this->dog->id, 'credits_issued' => 0]);
        OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_fail_confirm',
            'status'       => 'pending',
            'type'         => 'full',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('retrievePaymentIntent')
                ->once()
                ->andReturn((object) ['status' => 'succeeded', 'payment_method' => null]);
        });

        $this->mock(DogCreditService::class, function (MockInterface $mock) {
            $mock->shouldReceive('issueFromOrder')
                ->once()
                ->andThrow(new \RuntimeException('Credit ledger write failed'));
        });

        $this->actingAs($this->user);

        $response = $this->postJson('/my/purchase/confirm', [
            'payment_intent_id' => 'pi_fail_confirm',
        ]);

        // Must return non-200 so the Vue resp.ok guard triggers and shows an error to the user
        $this->assertNotEquals(200, $response->status());

        // Credits must NOT have been issued — dog still at 0
        $this->assertEquals(0, $this->dog->fresh()->credit_balance);
    }

}

