<?php

namespace Tests\Feature\Web\Portal;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SubscribeControllerTest extends TestCase
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
            'slug'               => 'testco',
            'status'             => 'active',
            'plan'               => 'starter',
            'stripe_account_id'  => 'acct_test123',
        ]);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'role'        => 'customer',
        ]);
        $this->customer->update(['user_id' => $this->user->id]);
        $this->dog = Dog::factory()->forCustomer($this->customer)->create();
    }

    public function test_index_renders_subscribe_page(): void
    {
        Package::factory()->subscription()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user);

        $response = $this->get('/my/subscribe');

        $response->assertInertia(fn ($page) => $page
            ->component('Portal/Subscribe')
            ->has('packages')
            ->has('dogs')
            ->has('stripe_key')
        );
    }

    public function test_index_only_shows_subscription_type_packages(): void
    {
        $sub = Package::factory()->subscription()->create(['tenant_id' => $this->tenant->id]);
        Package::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'one_time']);

        $this->actingAs($this->user);

        $response = $this->get('/my/subscribe');

        $response->assertInertia(fn ($page) => $page
            ->component('Portal/Subscribe')
            ->has('packages', 1)
            ->where('packages.0.id', $sub->id)
        );
    }

    public function test_store_creates_subscription_and_returns_client_secret(): void
    {
        $package = Package::factory()->subscription()->create([
            'tenant_id'               => $this->tenant->id,
            'stripe_price_id_monthly' => 'price_monthly_sub',
        ]);

        $stripe = $this->mock(StripeService::class);
        $stripe->shouldReceive('createCustomer')
            ->once()
            ->with(\Mockery::any(), \Mockery::any(), 'acct_test123')
            ->andReturn((object) ['id' => 'cus_test123']);
        $stripe->shouldReceive('createSetupIntent')
            ->once()
            ->with('cus_test123', \Mockery::any(), 'acct_test123')
            ->andReturn((object) ['client_secret' => 'seti_secret_test']);

        $this->actingAs($this->user);

        $response = $this->postJson('/my/subscribe', [
            'package_id' => $package->id,
            'dog_id'     => $this->dog->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['client_secret', 'subscription_id']);

        $this->assertDatabaseHas('subscriptions', [
            'dog_id'     => $this->dog->id,
            'package_id' => $package->id,
            'status'     => 'active',
        ]);
    }

    public function test_store_requires_stripe_price_id_monthly(): void
    {
        // A package without stripe_price_id_monthly (including one_time) cannot be subscribed to
        $package = Package::factory()->create([
            'tenant_id'               => $this->tenant->id,
            'type'                    => 'one_time',
            'stripe_price_id_monthly' => null,
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson('/my/subscribe', [
            'package_id' => $package->id,
            'dog_id'     => $this->dog->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error_code' => 'NO_MONTHLY_PRICE']);
    }

    public function test_store_allows_any_package_type_with_monthly_price(): void
    {
        // A one_time package that has stripe_price_id_monthly can be subscribed to
        $package = Package::factory()->create([
            'tenant_id'               => $this->tenant->id,
            'type'                    => 'one_time',
            'stripe_price_id_monthly' => 'price_monthly_test',
        ]);

        $stripe = $this->mock(StripeService::class);
        $stripe->shouldReceive('createCustomer')
            ->andReturn((object) ['id' => 'cus_test456']);
        $stripe->shouldReceive('createSetupIntent')
            ->once()
            ->andReturn((object) ['client_secret' => 'seti_secret_onetime']);

        $this->actingAs($this->user);

        $response = $this->postJson('/my/subscribe', [
            'package_id' => $package->id,
            'dog_id'     => $this->dog->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['client_secret', 'subscription_id']);
    }

    public function test_store_prevents_duplicate_active_subscription(): void
    {
        $package = Package::factory()->subscription()->create([
            'tenant_id'               => $this->tenant->id,
            'stripe_price_id_monthly' => 'price_monthly_dup',
        ]);

        Subscription::create([
            'tenant_id'  => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id' => $package->id,
            'dog_id'     => $this->dog->id,
            'status'     => 'active',
            'stripe_customer_id' => 'cus_existing',
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson('/my/subscribe', [
            'package_id' => $package->id,
            'dog_id'     => $this->dog->id,
        ]);

        $response->assertStatus(409);
        $response->assertJson(['error_code' => 'ALREADY_SUBSCRIBED']);
    }

    public function test_store_requires_tenant_stripe_account(): void
    {
        $this->tenant->update(['stripe_account_id' => null]);
        $package = Package::factory()->subscription()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user);

        $response = $this->postJson('/my/subscribe', [
            'package_id' => $package->id,
            'dog_id'     => $this->dog->id,
        ]);

        $response->assertStatus(422);
    }
}
