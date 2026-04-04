<?php

namespace Tests\Feature\Web;

use App\Models\PlatformConfig;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Services\StripeBillingService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class FoundersSlotEnforcementTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        PlatformConfig::create(['key' => 'trial_days', 'value' => '21', 'updated_at' => now()]);
    }

    private function createFoundersPlan(int $limit = 25): PlatformPlan
    {
        return PlatformPlan::factory()->create([
            'slug'                    => 'founders',
            'name'                    => 'Founders',
            'is_active'               => true,
            'sort_order'              => 0,
            'stripe_monthly_price_id' => 'price_founders_monthly',
            'tenant_limit'            => $limit,
            'monthly_gmv_cap_cents'   => 1_000_000,
            'default_platform_fee_pct'=> 2.00,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'business_name'         => 'Happy Paws Daycare',
            'slug'                  => 'happy-paws',
            'owner_name'            => 'Jane Smith',
            'email'                 => 'jane@happypaws.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'plan'                  => 'founders',
            'billing_cycle'         => 'monthly',
            'billing_address'       => [
                'street'      => '123 Main St',
                'city'        => 'Portland',
                'state'       => 'OR',
                'postal_code' => '97201',
                'country'     => 'US',
            ],
        ], $overrides);
    }

    public function test_registration_fails_when_founders_slots_are_full(): void
    {
        $this->createFoundersPlan(limit: 2);

        Tenant::factory()->count(2)->create(['plan' => 'founders', 'status' => 'active']);

        $this->mock(StripeBillingService::class)
            ->shouldNotReceive('createCustomer');

        $response = $this->post('/register', $this->validPayload());

        $response->assertSessionHasErrors(['plan']);
        $this->assertDatabaseCount('tenants', 2);
    }

    public function test_registration_succeeds_when_a_slot_is_available(): void
    {
        $this->createFoundersPlan(limit: 2);

        Tenant::factory()->create(['plan' => 'founders', 'status' => 'active']);

        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')->andReturn('cus_founders_123');
            $mock->shouldReceive('createTrialSubscription')->andReturn((object) ['id' => 'sub_founders_123']);
            $mock->shouldReceive('updateSubscriptionMetadata');
        });

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createConnectAccount')->andReturn((object) ['id' => 'acct_founders_123']);
        });

        $response = $this->post('/register', $this->validPayload());

        $this->assertDatabaseHas('tenants', ['slug' => 'happy-paws', 'plan' => 'founders']);
        $response->assertRedirect(route('tenant.register.success', ['slug' => 'happy-paws']));
    }

    public function test_cancelled_tenants_do_not_consume_slots(): void
    {
        $this->createFoundersPlan(limit: 1);

        Tenant::factory()->create(['plan' => 'founders', 'status' => 'cancelled']);

        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')->andReturn('cus_founders_456');
            $mock->shouldReceive('createTrialSubscription')->andReturn((object) ['id' => 'sub_founders_456']);
            $mock->shouldReceive('updateSubscriptionMetadata');
        });

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createConnectAccount')->andReturn((object) ['id' => 'acct_founders_456']);
        });

        $response = $this->post('/register', $this->validPayload());

        $this->assertDatabaseHas('tenants', ['slug' => 'happy-paws', 'plan' => 'founders']);
        $response->assertRedirect(route('tenant.register.success', ['slug' => 'happy-paws']));
    }

    public function test_registration_sets_default_platform_fee_from_plan(): void
    {
        $this->createFoundersPlan(limit: 25);

        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')->andReturn('cus_founders_789');
            $mock->shouldReceive('createTrialSubscription')->andReturn((object) ['id' => 'sub_founders_789']);
            $mock->shouldReceive('updateSubscriptionMetadata');
        });

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createConnectAccount')->andReturn((object) ['id' => 'acct_founders_789']);
        });

        $this->post('/register', $this->validPayload());

        $tenant = Tenant::where('slug', 'happy-paws')->firstOrFail();
        $this->assertEquals('2.00', $tenant->platform_fee_pct);
    }

    public function test_unlimited_plans_never_block_registration(): void
    {
        PlatformPlan::factory()->create([
            'slug'                    => 'starter',
            'name'                    => 'Starter',
            'is_active'               => true,
            'stripe_monthly_price_id' => 'price_starter_monthly',
            'tenant_limit'            => null,
        ]);

        // Create many tenants — should not matter
        Tenant::factory()->count(100)->create(['plan' => 'starter', 'status' => 'active']);

        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')->andReturn('cus_starter_999');
            $mock->shouldReceive('createTrialSubscription')->andReturn((object) ['id' => 'sub_starter_999']);
            $mock->shouldReceive('updateSubscriptionMetadata');
        });

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createConnectAccount')->andReturn((object) ['id' => 'acct_starter_999']);
        });

        $response = $this->post('/register', $this->validPayload(['plan' => 'starter']));

        $this->assertDatabaseHas('tenants', ['slug' => 'happy-paws', 'plan' => 'starter']);
        $response->assertRedirect(route('tenant.register.success', ['slug' => 'happy-paws']));
    }
}
