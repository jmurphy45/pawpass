<?php

namespace Tests\Feature\Portal;

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
use Tests\Traits\InteractsWithJwt;

class SubscriptionControllerStripeTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private Dog $dog;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create([
            'slug' => 'substripe',
            'status' => 'active',
            'stripe_account_id' => 'acct_substripe',
            'platform_fee_pct' => '5.00',
        ]);
        URL::forceRootUrl('http://substripe.pawpass.com');

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'owner@example.com',
            'name' => 'Test Owner',
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'role' => 'customer',
        ]);

        $this->dog = Dog::factory()->forCustomer($this->customer)->withCredits(0)->create();

        $this->package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'subscription',
            'price' => '99.00',
            'is_active' => true,
            'stripe_price_id' => 'price_test123',
        ]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->user)];
    }

    public function test_store_reuses_existing_stripe_customer_id(): void
    {
        $this->customer->update(['stripe_customer_id' => 'cus_existing']);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('createCustomer');

            $mock->shouldReceive('createSetupIntent')
                ->once()
                ->with('cus_existing', \Mockery::any(), 'acct_substripe')
                ->andReturn((object) ['id' => 'si_test', 'client_secret' => 'si_secret']);
        });

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/portal/v1/subscriptions', [
                'package_id' => $this->package->id,
                'dog_id' => $this->dog->id,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('subscriptions', [
            'customer_id' => $this->customer->id,
            'stripe_customer_id' => 'cus_existing',
        ]);
    }

    public function test_store_creates_and_stores_stripe_customer_when_none_exists(): void
    {
        // No stripe_customer_id on customer by default

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')
                ->once()
                ->with('owner@example.com', 'Test Owner', 'acct_substripe')
                ->andReturn((object) ['id' => 'cus_brand_new']);

            $mock->shouldReceive('createSetupIntent')
                ->once()
                ->with('cus_brand_new', \Mockery::any(), 'acct_substripe')
                ->andReturn((object) ['id' => 'si_new', 'client_secret' => 'si_new_secret']);
        });

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/portal/v1/subscriptions', [
                'package_id' => $this->package->id,
                'dog_id' => $this->dog->id,
            ]);

        $response->assertStatus(201);

        // ID stored on customer record
        $this->assertDatabaseHas('customers', [
            'id' => $this->customer->id,
            'stripe_customer_id' => 'cus_brand_new',
        ]);

        // ID stored on subscription
        $this->assertDatabaseHas('subscriptions', [
            'customer_id' => $this->customer->id,
            'stripe_customer_id' => 'cus_brand_new',
        ]);
    }
}
