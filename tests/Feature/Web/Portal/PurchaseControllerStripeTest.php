<?php

namespace Tests\Feature\Web\Portal;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Tests\TestCase;

class PurchaseControllerStripeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private Dog $dog;

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
                )
                ->andReturn((object) ['id' => 'pi_test', 'client_secret' => 'pi_secret']);
        });

        $this->actingAs($this->user);

        $response = $this->postJson('/my/purchase', [
            'package_id' => $package->id,
            'dog_id'     => $this->dog->id,
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
                )
                ->andReturn((object) ['id' => 'pi_reuse', 'client_secret' => 'pi_reuse_secret']);
        });

        $this->actingAs($this->user);

        $response = $this->postJson('/my/purchase', [
            'package_id' => $package->id,
            'dog_id'     => $this->dog->id,
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
            'dog_id'     => $this->dog->id,
        ]);

        // 3rd positional arg is stripeAccountId (not transferDestination)
        $this->assertEquals('acct_purchase123', $capturedPayload[2]);
    }
}
