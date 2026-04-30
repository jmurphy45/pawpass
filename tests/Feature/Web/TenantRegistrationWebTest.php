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

class TenantRegistrationWebTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        PlatformPlan::factory()->create([
            'slug' => 'starter',
            'name' => 'Starter',
            'is_active' => true,
            'sort_order' => 1,
            'stripe_monthly_price_id' => 'price_starter_monthly',
            'stripe_annual_price_id' => 'price_starter_annual',
        ]);

        PlatformConfig::create(['key' => 'trial_days', 'value' => '21', 'updated_at' => now()]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Happy Paws Daycare',
            'slug' => 'happy-paws',
            'timezone' => 'America/Chicago',
            'owner_name' => 'Jane Smith',
            'email' => 'jane@happypaws.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'plan' => 'starter',
            'billing_cycle' => 'monthly',
            'billing_address' => [
                'street' => '123 Main St',
                'city' => 'Portland',
                'state' => 'OR',
                'postal_code' => '97201',
                'country' => 'US',
            ],
        ], $overrides);
    }

    public function test_registration_page_renders_with_plans(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Registration/Create')
            ->has('plans', 1)
            ->has('trialDays')
        );
    }

    public function test_web_registration_creates_tenant_and_redirects(): void
    {
        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')->andReturn('cus_web_123');
            $mock->shouldReceive('createTrialSubscription')->andReturn((object) ['id' => 'sub_web_123']);
            $mock->shouldReceive('updateSubscriptionMetadata');
        });

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createConnectAccount')->andReturn((object) ['id' => 'acct_web_123']);
        });

        $response = $this->post('/register', $this->validPayload());

        $this->assertNotNull(Tenant::where('slug', 'happy-paws')->first());
        $response->assertRedirect(route('tenant.register.success', ['slug' => 'happy-paws']));
    }

    public function test_web_registration_validates_slug_uniqueness(): void
    {
        Tenant::factory()->create(['slug' => 'taken-slug']);

        $response = $this->post('/register', $this->validPayload(['slug' => 'taken-slug']));

        $response->assertSessionHasErrors(['slug']);
    }

    public function test_web_registration_requires_active_synced_plan(): void
    {
        PlatformPlan::factory()->create([
            'slug' => 'unsynced',
            'is_active' => true,
            'stripe_monthly_price_id' => null,
        ]);

        $response = $this->post('/register', $this->validPayload(['plan' => 'unsynced']));

        $response->assertSessionHasErrors(['plan']);
    }
}
