<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class PlatformPlanControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        URL::forceRootUrl('http://platform.pawpass.com');

        $this->admin = User::factory()->platformAdmin()->create();
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->admin)];
    }

    public function test_platform_admin_can_list_plans(): void
    {
        PlatformPlan::factory()->count(2)->create(['is_active' => true]);
        PlatformPlan::factory()->create(['is_active' => false]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/platform/v1/plans');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_platform_admin_can_sync_plan_to_stripe(): void
    {
        $plan = PlatformPlan::factory()->create([
            'slug'                    => 'starter',
            'name'                    => 'Starter',
            'stripe_product_id'       => null,
            'stripe_monthly_price_id' => null,
            'stripe_annual_price_id'  => null,
        ]);

        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPlatformProduct')
                ->once()
                ->andReturn('prod_new_abc');

            $mock->shouldReceive('createPlatformPrice')
                ->twice()
                ->andReturn('price_new_monthly', 'price_new_annual');
        });

        $response = $this->withHeaders($this->headers())
            ->postJson("/api/platform/v1/plans/{$plan->id}/sync-stripe");

        $response->assertStatus(200);
    }

    public function test_sync_stores_stripe_ids_on_plan(): void
    {
        $plan = PlatformPlan::factory()->create([
            'stripe_product_id'       => null,
            'stripe_monthly_price_id' => null,
            'stripe_annual_price_id'  => null,
        ]);

        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPlatformProduct')->andReturn('prod_stored_xyz');
            $mock->shouldReceive('createPlatformPrice')->andReturn('price_mo_stored', 'price_yr_stored');
        });

        $this->withHeaders($this->headers())
            ->postJson("/api/platform/v1/plans/{$plan->id}/sync-stripe");

        $plan->refresh();
        $this->assertEquals('prod_stored_xyz', $plan->stripe_product_id);
        $this->assertEquals('price_mo_stored', $plan->stripe_monthly_price_id);
        $this->assertEquals('price_yr_stored', $plan->stripe_annual_price_id);
    }

    public function test_store_creates_plan_with_stripe_ids_immediately(): void
    {
        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPlatformProduct')->once()->andReturn('prod_new_123');
            $mock->shouldReceive('createPlatformPrice')
                ->twice()
                ->andReturn('price_mo_new', 'price_yr_new');
        });

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/platform/v1/plans', [
                'slug'                => 'premium',
                'name'                => 'Premium',
                'monthly_price_cents' => 9900,
                'annual_price_cents'  => 99000,
                'features'            => ['add_customers', 'add_dogs'],
            ]);

        $response->assertStatus(201);

        // Stripe IDs must be populated immediately (observer runs job synchronously via sync queue)
        $this->assertDatabaseHas('platform_plans', [
            'slug'                    => 'premium',
            'stripe_monthly_price_id' => 'price_mo_new',
            'stripe_annual_price_id'  => 'price_yr_new',
        ]);
    }

    public function test_platform_admin_can_update_sms_cost_per_segment_cents(): void
    {
        $plan = PlatformPlan::factory()->create(['sms_cost_per_segment_cents' => 4]);

        $response = $this->withHeaders($this->headers())
            ->patchJson("/api/platform/v1/plans/{$plan->id}", [
                'sms_cost_per_segment_cents' => 2,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.sms_cost_per_segment_cents', 2);
        $this->assertDatabaseHas('platform_plans', [
            'id'                         => $plan->id,
            'sms_cost_per_segment_cents' => 2,
        ]);
    }

    public function test_plan_resource_exposes_sms_fields(): void
    {
        PlatformPlan::factory()->create(['sms_segment_quota' => 500, 'sms_cost_per_segment_cents' => 3]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/platform/v1/plans');

        $response->assertStatus(200);
        $plan = $response->json('data.0');
        $this->assertArrayHasKey('sms_segment_quota', $plan);
        $this->assertArrayHasKey('sms_cost_per_segment_cents', $plan);
        $this->assertSame(500, $plan['sms_segment_quota']);
        $this->assertSame(3, $plan['sms_cost_per_segment_cents']);
    }

    public function test_non_platform_admin_cannot_manage_plans(): void
    {
        $tenant = Tenant::factory()->create();
        $owner  = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'business_owner']);

        $headers = ['Authorization' => 'Bearer '.$this->jwtFor($owner)];

        $this->withHeaders($headers)->getJson('/api/platform/v1/plans')->assertStatus(403);
    }
}
