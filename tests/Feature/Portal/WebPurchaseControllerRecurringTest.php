<?php

namespace Tests\Feature\Portal;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Tests\TestCase;

class WebPurchaseControllerRecurringTest extends TestCase
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
            'slug'              => 'recpurchasetest',
            'status'            => 'active',
            'plan'              => 'starter',
            'stripe_account_id' => 'acct_recpurchase',
            'platform_fee_pct'  => '5.00',
        ]);
        URL::forceRootUrl('http://recpurchasetest.pawpass.com');

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Recurring Buyer',
            'email'     => 'rec@example.com',
        ]);
        $this->user = User::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'role'        => 'customer',
        ]);
        $this->customer->update(['user_id' => $this->user->id]);
        $this->dog = Dog::factory()->forCustomer($this->customer)->create();
    }

    public function test_one_time_package_with_recurring_enabled_billing_mode_returns_setup_intent(): void
    {
        $package = Package::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'type'                     => 'one_time',
            'price'                    => '89.00',
            'credit_count'             => 10,
            'is_active'                => true,
            'is_recurring_enabled'     => true,
            'stripe_price_id_recurring' => 'price_rec_30d',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')
                ->once()
                ->andReturn((object) ['id' => 'cus_rec_new']);

            $mock->shouldReceive('createSetupIntent')
                ->once()
                ->with('cus_rec_new', \Mockery::any(), 'acct_recpurchase')
                ->andReturn((object) ['client_secret' => 'seti_secret_rec']);

            $mock->shouldNotReceive('createPaymentIntent');
        });

        $this->actingAs($this->user);

        $response = $this->postJson('/my/purchase', [
            'package_id'   => $package->id,
            'dog_ids'      => [$this->dog->id],
            'billing_mode' => 'recurring',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['client_secret', 'subscription_id']);

        $this->assertDatabaseHas('subscriptions', [
            'dog_id'     => $this->dog->id,
            'package_id' => $package->id,
            'status'     => 'active',
        ]);
    }

    public function test_one_time_package_with_recurring_disabled_returns_422(): void
    {
        $package = Package::factory()->create([
            'tenant_id'            => $this->tenant->id,
            'type'                 => 'one_time',
            'price'                => '89.00',
            'credit_count'         => 10,
            'is_active'            => true,
            'is_recurring_enabled' => false,
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson('/my/purchase', [
            'package_id'   => $package->id,
            'dog_ids'      => [$this->dog->id],
            'billing_mode' => 'recurring',
        ]);

        $response->assertStatus(422);
    }

    public function test_unlimited_package_with_recurring_enabled_returns_setup_intent(): void
    {
        $package = Package::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'type'                     => 'unlimited',
            'price'                    => '150.00',
            'duration_days'            => 30,
            'is_active'                => true,
            'is_recurring_enabled'     => true,
            'stripe_price_id_recurring' => 'price_unl_rec_30d',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')
                ->once()
                ->andReturn((object) ['id' => 'cus_unl_rec']);

            $mock->shouldReceive('createSetupIntent')
                ->once()
                ->andReturn((object) ['client_secret' => 'seti_unl_rec']);

            $mock->shouldNotReceive('createPaymentIntent');
        });

        $this->actingAs($this->user);

        $response = $this->postJson('/my/purchase', [
            'package_id'   => $package->id,
            'dog_ids'      => [$this->dog->id],
            'billing_mode' => 'recurring',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['client_secret', 'subscription_id']);
    }

    public function test_recurring_mode_with_no_recurring_price_id_returns_422(): void
    {
        $package = Package::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'type'                     => 'one_time',
            'price'                    => '89.00',
            'credit_count'             => 10,
            'is_active'                => true,
            'is_recurring_enabled'     => true,
            'stripe_price_id_recurring' => null,
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson('/my/purchase', [
            'package_id'   => $package->id,
            'dog_ids'      => [$this->dog->id],
            'billing_mode' => 'recurring',
        ]);

        $response->assertStatus(422);
    }

    public function test_index_includes_recurring_fields_in_packages(): void
    {
        Package::factory()->create([
            'tenant_id'              => $this->tenant->id,
            'type'                   => 'one_time',
            'is_active'              => true,
            'is_recurring_enabled'   => true,
            'recurring_interval_days' => 14,
        ]);

        $this->actingAs($this->user);

        $response = $this->get('/my/purchase');

        $response->assertInertia(fn ($page) => $page
            ->component('Portal/Purchase')
            ->has('packages', 1)
            ->where('packages.0.is_recurring_enabled', true)
            ->where('packages.0.recurring_interval_days', 14)
        );
    }
}
