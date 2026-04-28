<?php

namespace Tests\Feature\Admin;

use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class CustomerIntelligenceControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $freeTenant;

    private Tenant $starterTenant;

    private Tenant $proTenant;

    private User $freeOwner;

    private User $starterOwner;

    private User $proOwner;

    private User $proStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        PlatformPlan::factory()->create(['slug' => 'free', 'features' => [], 'staff_limit' => 1]);
        PlatformPlan::factory()->create([
            'slug' => 'starter',
            'features' => ['add_customers', 'add_dogs', 'basic_reporting'],
            'staff_limit' => 5,
        ]);
        PlatformPlan::factory()->create([
            'slug' => 'pro',
            'features' => ['add_customers', 'add_dogs', 'basic_reporting', 'financial_reports'],
            'staff_limit' => 15,
        ]);

        $this->freeTenant = Tenant::factory()->create(['slug' => 'free-ci', 'status' => 'free_tier', 'plan' => 'free']);
        $this->starterTenant = Tenant::factory()->create(['slug' => 'starter-ci', 'status' => 'active', 'plan' => 'starter']);
        $this->proTenant = Tenant::factory()->create(['slug' => 'pro-ci', 'status' => 'active', 'plan' => 'pro']);

        $this->freeOwner = User::factory()->create([
            'tenant_id' => $this->freeTenant->id,
            'role' => 'business_owner',
        ]);
        $this->starterOwner = User::factory()->create([
            'tenant_id' => $this->starterTenant->id,
            'role' => 'business_owner',
        ]);
        $this->proOwner = User::factory()->create([
            'tenant_id' => $this->proTenant->id,
            'role' => 'business_owner',
        ]);
        $this->proStaff = User::factory()->create([
            'tenant_id' => $this->proTenant->id,
            'role' => 'staff',
        ]);
    }

    protected function tearDown(): void
    {
        Feature::flushCache();
        parent::tearDown();
    }

    private function authFor(User $user, Tenant $tenant): array
    {
        URL::forceRootUrl("http://{$tenant->slug}.pawpass.com");

        return ['Authorization' => 'Bearer '.$this->jwtFor($user)];
    }

    public function test_returns_200_with_correct_structure_for_pro_owner(): void
    {
        $response = $this->withHeaders($this->authFor($this->proOwner, $this->proTenant))
            ->getJson('/api/admin/v1/reports/customer-intelligence');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['churn_risk', 'price_sensitivity', 'package_fit'],
                'meta',
            ]);
    }

    public function test_returns_empty_arrays_when_tenant_has_no_data(): void
    {
        $response = $this->withHeaders($this->authFor($this->proOwner, $this->proTenant))
            ->getJson('/api/admin/v1/reports/customer-intelligence');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'churn_risk' => [],
                    'price_sensitivity' => [],
                    'package_fit' => [],
                ],
            ]);
    }

    public function test_returns_403_for_starter_owner(): void
    {
        $response = $this->withHeaders($this->authFor($this->starterOwner, $this->starterTenant))
            ->getJson('/api/admin/v1/reports/customer-intelligence');

        $response->assertStatus(403);
    }

    public function test_returns_403_for_free_owner(): void
    {
        $response = $this->withHeaders($this->authFor($this->freeOwner, $this->freeTenant))
            ->getJson('/api/admin/v1/reports/customer-intelligence');

        $response->assertStatus(403);
    }

    public function test_returns_403_for_pro_staff(): void
    {
        $response = $this->withHeaders($this->authFor($this->proStaff, $this->proTenant))
            ->getJson('/api/admin/v1/reports/customer-intelligence');

        $response->assertStatus(403);
    }
}
