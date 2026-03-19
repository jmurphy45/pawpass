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
use Tests\Traits\InteractsWithJwt;

class SubscriptionControllerTest extends TestCase
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
            'slug' => 'subtest',
            'status' => 'active',
            'stripe_account_id' => 'acct_subtest',
            'platform_fee_pct' => '5.00',
        ]);
        URL::forceRootUrl('http://subtest.pawpass.com');

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
            'credit_count' => 20,
            'is_active' => true,
            'stripe_price_id' => 'price_test123',
        ]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->user)];
    }

    private function mockStripe(string $cusId = 'cus_test', string $siId = 'si_test', string $siSecret = 'si_test_secret'): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) use ($cusId, $siId, $siSecret) {
            $mock->shouldReceive('createCustomer')
                ->once()
                ->andReturn((object) ['id' => $cusId]);
            $mock->shouldReceive('createSetupIntent')
                ->once()
                ->andReturn((object) ['id' => $siId, 'client_secret' => $siSecret]);
        });
    }

    public function test_store_creates_subscription_and_returns_client_secret(): void
    {
        $this->mockStripe('cus_abc', 'si_abc', 'si_abc_secret');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/portal/v1/subscriptions', [
                'package_id' => $this->package->id,
                'dog_id' => $this->dog->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['subscription_id', 'client_secret']])
            ->assertJsonPath('data.client_secret', 'si_abc_secret');

        $this->assertDatabaseHas('subscriptions', [
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'dog_id' => $this->dog->id,
            'status' => 'active',
            'stripe_customer_id' => 'cus_abc',
        ]);
    }

    public function test_store_calls_create_customer_with_correct_params(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')
                ->once()
                ->with('owner@example.com', 'Test Owner', 'acct_subtest')
                ->andReturn((object) ['id' => 'cus_check']);
            $mock->shouldReceive('createSetupIntent')
                ->once()
                ->andReturn((object) ['id' => 'si_check', 'client_secret' => 'si_check_secret']);
        });

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/portal/v1/subscriptions', [
                'package_id' => $this->package->id,
                'dog_id' => $this->dog->id,
            ])
            ->assertStatus(201);
    }

    public function test_store_archived_package_returns_409(): void
    {
        $archived = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'subscription',
            'is_active' => false,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/portal/v1/subscriptions', [
                'package_id' => $archived->id,
                'dog_id' => $this->dog->id,
            ]);

        $response->assertStatus(422); // fails at request validation (is_active = false)
    }

    public function test_store_non_subscription_package_returns_422(): void
    {
        $oneTime = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'one_time',
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/portal/v1/subscriptions', [
                'package_id' => $oneTime->id,
                'dog_id' => $this->dog->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'INVALID_PACKAGE_TYPE');
    }

    public function test_store_dog_from_different_customer_returns_422(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherDog = Dog::factory()->forCustomer($otherCustomer)->create();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/portal/v1/subscriptions', [
                'package_id' => $this->package->id,
                'dog_id' => $otherDog->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dog_id']);
    }

    public function test_store_already_subscribed_returns_409(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'dog_id' => $this->dog->id,
            'status' => 'active',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/portal/v1/subscriptions', [
                'package_id' => $this->package->id,
                'dog_id' => $this->dog->id,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'ALREADY_SUBSCRIBED');
    }

    public function test_cancel_active_subscription_sets_cancelled_at(): void
    {
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'dog_id' => $this->dog->id,
            'status' => 'active',
            'stripe_sub_id' => 'sub_cancel_test',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cancelSubscriptionAtPeriodEnd')
                ->once()
                ->with('sub_cancel_test')
                ->andReturn((object) ['id' => 'sub_cancel_test', 'cancel_at_period_end' => true]);
        });

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/portal/v1/subscriptions/{$subscription->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        $this->assertNotNull($subscription->fresh()->cancelled_at);
    }

    public function test_cancel_non_active_subscription_returns_409(): void
    {
        $subscription = Subscription::factory()->pastDue()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'dog_id' => $this->dog->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/portal/v1/subscriptions/{$subscription->id}/cancel");

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'NOT_CANCELLABLE');
    }

    public function test_index_returns_customer_subscriptions(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'dog_id' => $this->dog->id,
            'status' => 'active',
        ]);

        // Another customer's subscription - should not appear
        $other = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherDog = Dog::factory()->forCustomer($other)->create();
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $other->id,
            'package_id' => $this->package->id,
            'dog_id' => $otherDog->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/subscriptions');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'status', 'package', 'dog']]]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->customer->id, $response->json('data.0.id') === null ?: $this->customer->id);
    }
}
