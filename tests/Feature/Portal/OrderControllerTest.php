<?php

namespace Tests\Feature\Portal;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Order;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class OrderControllerTest extends TestCase
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
            'slug' => 'ordertest',
            'status' => 'active',
            'stripe_account_id' => 'acct_test123',
            'stripe_onboarded_at' => now(),
            'platform_fee_pct' => '5.00',
        ]);
        URL::forceRootUrl('http://ordertest.pawpass.com');

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'role' => 'customer',
        ]);

        $this->dog = Dog::factory()->forCustomer($this->customer)->withCredits(0)->create();

        $this->package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'one_time',
            'price' => '50.00',
            'credit_count' => 10,
            'is_active' => true,
        ]);
    }

    private function authHeaders(?string $idempotencyKey = null): array
    {
        $headers = ['Authorization' => 'Bearer '.$this->jwtFor($this->user)];
        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }

    private function mockStripe(string $piId = 'pi_test123', string $clientSecret = 'pi_test123_secret_abc'): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) use ($piId, $clientSecret) {
            $mock->shouldReceive('createCustomer')
                ->zeroOrMoreTimes()
                ->andReturn((object) ['id' => 'cus_new123']);
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturn((object) ['id' => $piId, 'client_secret' => $clientSecret]);
        });
    }

    public function test_creates_order_and_returns_client_secret(): void
    {
        $this->mockStripe();

        $response = $this->withHeaders($this->authHeaders('idem-key-1'))
            ->postJson('/api/portal/v1/orders', [
                'package_id' => $this->package->id,
                'dog_ids' => [$this->dog->id],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['order_id', 'client_secret']]);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'status' => 'pending',
        ]);

        $orderId = $response->json('data.order_id');
        $this->assertDatabaseHas('order_dogs', [
            'order_id' => $orderId,
            'dog_id' => $this->dog->id,
        ]);
    }

    public function test_archived_package_returns_409(): void
    {
        $archived = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'one_time',
            'is_active' => false,
        ]);

        $response = $this->withHeaders($this->authHeaders('idem-key-archived'))
            ->postJson('/api/portal/v1/orders', [
                'package_id' => $archived->id,
                'dog_ids' => [$this->dog->id],
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'PACKAGE_ARCHIVED');
    }

    public function test_subscription_package_returns_422(): void
    {
        $sub = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'subscription',
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders('idem-key-sub'))
            ->postJson('/api/portal/v1/orders', [
                'package_id' => $sub->id,
                'dog_ids' => [$this->dog->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'INVALID_PACKAGE_TYPE');
    }

    public function test_dog_from_different_customer_returns_422(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherDog = Dog::factory()->forCustomer($otherCustomer)->create();

        $response = $this->withHeaders($this->authHeaders('idem-key-wrong-dog'))
            ->postJson('/api/portal/v1/orders', [
                'package_id' => $this->package->id,
                'dog_ids' => [$otherDog->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dog_ids.0']);
    }

    public function test_missing_idempotency_key_returns_400(): void
    {
        $response = $this->withHeaders($this->authHeaders()) // no key
            ->postJson('/api/portal/v1/orders', [
                'package_id' => $this->package->id,
                'dog_ids' => [$this->dog->id],
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error_code', 'IDEMPOTENCY_KEY_REQUIRED');
    }

    public function test_idempotency_replay_returns_same_order_without_second_stripe_call(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')
                ->once()
                ->andReturn((object) ['id' => 'cus_replay123']);
            $mock->shouldReceive('createPaymentIntent')
                ->once() // only called once
                ->andReturn((object) ['id' => 'pi_replay123', 'client_secret' => 'pi_replay123_secret']);
        });

        $first = $this->withHeaders($this->authHeaders('idem-replay-key'))
            ->postJson('/api/portal/v1/orders', [
                'package_id' => $this->package->id,
                'dog_ids' => [$this->dog->id],
            ]);

        $first->assertStatus(201);
        $orderId = $first->json('data.order_id');

        $second = $this->withHeaders($this->authHeaders('idem-replay-key'))
            ->postJson('/api/portal/v1/orders', [
                'package_id' => $this->package->id,
                'dog_ids' => [$this->dog->id],
            ]);

        $second->assertStatus(201)
            ->assertJsonPath('data.order_id', $orderId);

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_index_returns_customer_orders(): void
    {
        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/orders');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'status', 'total_amount']]]);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_creates_stripe_customer_when_none_exists(): void
    {
        $this->assertNull($this->customer->stripe_customer_id);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')
                ->once()
                ->andReturn((object) ['id' => 'cus_new456']);
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturn((object) ['id' => 'pi_new456', 'client_secret' => 'pi_new456_secret']);
        });

        $this->withHeaders($this->authHeaders('idem-new-cus'))
            ->postJson('/api/portal/v1/orders', [
                'package_id' => $this->package->id,
                'dog_ids' => [$this->dog->id],
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('customers', [
            'id' => $this->customer->id,
            'stripe_customer_id' => 'cus_new456',
        ]);
    }

    public function test_reuses_existing_stripe_customer(): void
    {
        $this->customer->update(['stripe_customer_id' => 'cus_existing789']);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')->never();
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturn((object) ['id' => 'pi_reuse789', 'client_secret' => 'pi_reuse789_secret']);
        });

        $this->withHeaders($this->authHeaders('idem-reuse-cus'))
            ->postJson('/api/portal/v1/orders', [
                'package_id' => $this->package->id,
                'dog_ids' => [$this->dog->id],
            ])
            ->assertStatus(201);
    }

    public function test_inactive_dog_is_rejected_in_order(): void
    {
        $this->dog->update(['status' => 'inactive']);

        $this->withHeaders($this->authHeaders('idem-inactive'))
            ->postJson('/api/portal/v1/orders', [
                'package_id' => $this->package->id,
                'dog_ids' => [$this->dog->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('dog_ids.0');
    }

    public function test_suspended_dog_is_rejected_in_order(): void
    {
        $this->dog->update(['status' => 'suspended']);

        $this->withHeaders($this->authHeaders('idem-suspended'))
            ->postJson('/api/portal/v1/orders', [
                'package_id' => $this->package->id,
                'dog_ids' => [$this->dog->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('dog_ids.0');
    }

    public function test_successful_order_records_first_purchase_event(): void
    {
        $this->mockStripe('pi_fp1');

        $this->withHeaders($this->authHeaders('idem-fp-1'))
            ->postJson('/api/portal/v1/orders', [
                'package_id' => $this->package->id,
                'dog_ids' => [$this->dog->id],
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('tenant_events', [
            'tenant_id' => $this->tenant->id,
            'event_type' => 'first_purchase',
        ]);
        $this->assertDatabaseCount('tenant_events', 1);
    }

    public function test_payment_intent_restricts_to_card_and_bank_payment_methods(): void
    {
        $capturedTypes = null;

        $this->mock(StripeService::class, function (MockInterface $mock) use (&$capturedTypes) {
            $mock->shouldReceive('createCustomer')->zeroOrMoreTimes()->andReturn((object) ['id' => 'cus_pm2']);
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturnUsing(function () use (&$capturedTypes) {
                    // paymentMethodTypes is the 10th positional argument (index 9)
                    $capturedTypes = func_get_arg(9);

                    return (object) ['id' => 'pi_pm2', 'client_secret' => 'secret_pm2'];
                });
        });

        $this->withHeaders($this->authHeaders('idem-pm-types'))
            ->postJson('/api/portal/v1/orders', [
                'package_id' => $this->package->id,
                'dog_ids' => [$this->dog->id],
            ])
            ->assertStatus(201);

        $this->assertEquals(['card', 'us_bank_account'], $capturedTypes);
    }
}
