<?php

namespace Tests\Feature\Public;

use App\Auth\JwtService;
use App\Models\PlatformConfig;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscriptionEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeBillingService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class TenantRegistrationControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private PlatformPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->plan = PlatformPlan::factory()->create([
            'slug'                    => 'starter',
            'name'                    => 'Starter',
            'is_active'               => true,
            'stripe_monthly_price_id' => 'price_starter_monthly',
            'stripe_annual_price_id'  => 'price_starter_annual',
        ]);

        PlatformConfig::create(['key' => 'trial_days', 'value' => '21', 'updated_at' => now()]);
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
            'plan'                  => 'starter',
            'billing_cycle'         => 'monthly',
        ], $overrides);
    }

    private function mockBilling(): void
    {
        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')->andReturn('cus_new_123');
            $mock->shouldReceive('createTrialSubscription')->andReturn((object) ['id' => 'sub_trial_123']);
            $mock->shouldReceive('updateSubscriptionMetadata');
        });

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createConnectAccount')->andReturn((object) ['id' => 'acct_new_123']);
        });
    }

    public function test_can_register_new_tenant_with_valid_data(): void
    {
        $this->mockBilling();

        $response = $this->postJson('/api/public/v1/tenants/register', $this->validPayload());

        $response->assertStatus(201);

        $tenant = Tenant::where('slug', 'happy-paws')->first();
        $this->assertNotNull($tenant);
        $this->assertEquals('trialing', $tenant->status);

        $user = User::where('email', 'jane@happypaws.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('business_owner', $user->role);
        $this->assertEquals($tenant->id, $user->tenant_id);
    }

    public function test_registration_creates_stripe_customer_and_trial_subscription(): void
    {
        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')
                ->once()
                ->andReturn('cus_check_123');

            $mock->shouldReceive('createTrialSubscription')
                ->once()
                ->withArgs(function ($tenant, $priceId, $cycle, $trialDays) {
                    return $trialDays === 21 && $cycle === 'monthly';
                })
                ->andReturn((object) ['id' => 'sub_check_123']);

            $mock->shouldReceive('updateSubscriptionMetadata');
        });

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createConnectAccount')->andReturn((object) ['id' => 'acct_check_123']);
        });

        $this->postJson('/api/public/v1/tenants/register', $this->validPayload())
            ->assertStatus(201);
    }

    public function test_registration_stores_stripe_ids_on_tenant(): void
    {
        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')->andReturn('cus_stored_456');
            $mock->shouldReceive('createTrialSubscription')->andReturn((object) ['id' => 'sub_stored_456']);
            $mock->shouldReceive('updateSubscriptionMetadata');
        });

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createConnectAccount')->andReturn((object) ['id' => 'acct_stored_456']);
        });

        $this->postJson('/api/public/v1/tenants/register', $this->validPayload())
            ->assertStatus(201);

        $tenant = Tenant::where('slug', 'happy-paws')->first();
        $this->assertEquals('cus_stored_456', $tenant->platform_stripe_customer_id);
        $this->assertEquals('sub_stored_456', $tenant->platform_stripe_sub_id);
    }

    public function test_registration_sets_trial_ends_at_based_on_config(): void
    {
        $this->mockBilling();

        $before = now()->addDays(21)->startOfDay();

        $this->postJson('/api/public/v1/tenants/register', $this->validPayload())
            ->assertStatus(201);

        $tenant = Tenant::where('slug', 'happy-paws')->first();
        $this->assertNotNull($tenant->trial_ends_at);
        $this->assertTrue($tenant->trial_ends_at->gte($before));
        $this->assertTrue($tenant->trial_ends_at->lte(now()->addDays(21)->endOfDay()));
    }

    public function test_slug_must_be_unique(): void
    {
        Tenant::factory()->create(['slug' => 'taken-slug']);

        $response = $this->postJson('/api/public/v1/tenants/register', $this->validPayload(['slug' => 'taken-slug']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_slug_must_match_pattern(): void
    {
        $response = $this->postJson('/api/public/v1/tenants/register', $this->validPayload(['slug' => 'My Daycare!']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_email_must_be_unique(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'jane@happypaws.com']);

        $response = $this->postJson('/api/public/v1/tenants/register', $this->validPayload());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_plan_must_be_active_and_stripe_synced(): void
    {
        PlatformPlan::factory()->create([
            'slug'                    => 'unsynced',
            'is_active'               => true,
            'stripe_monthly_price_id' => null,
        ]);

        $response = $this->postJson('/api/public/v1/tenants/register', $this->validPayload(['plan' => 'unsynced']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);
    }

    public function test_response_includes_jwt_access_token(): void
    {
        $this->mockBilling();

        $response = $this->postJson('/api/public/v1/tenants/register', $this->validPayload());

        $response->assertStatus(201);
        $token = $response->json('data.access_token');
        $this->assertNotNull($token);

        // Decode and verify it's valid
        $jwtService = app(JwtService::class);
        $decoded    = $jwtService->decode($token);
        $this->assertEquals('business_owner', $decoded->role);
    }

    public function test_registration_records_platform_subscription_event(): void
    {
        $this->mockBilling();

        $this->postJson('/api/public/v1/tenants/register', $this->validPayload())
            ->assertStatus(201);

        $tenant = Tenant::where('slug', 'happy-paws')->first();

        $event = PlatformSubscriptionEvent::where('tenant_id', $tenant->id)
            ->where('event_type', 'trial_started')
            ->first();

        $this->assertNotNull($event);
    }
}
