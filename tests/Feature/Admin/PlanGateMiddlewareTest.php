<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\PlatformFeature;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class PlanGateMiddlewareTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        PlatformPlan::factory()->create(['slug' => 'free', 'features' => [], 'staff_limit' => 1]);
        $starterFeatures = ['add_customers', 'add_dogs', 'customer_portal', 'email_notifications', 'basic_reporting'];
        $starter = PlatformPlan::factory()->create([
            'slug'        => 'starter',
            'features'    => $starterFeatures,
            'staff_limit' => 5,
        ]);
        $featureIds = collect($starterFeatures)->map(fn ($s) =>
            PlatformFeature::firstOrCreate(['slug' => $s], ['name' => $s, 'sort_order' => 0])->id
        );
        $starter->features()->sync($featureIds);
    }

    protected function tearDown(): void
    {
        Feature::flushCache();
        parent::tearDown();
    }

    private function makeStaffFor(Tenant $tenant): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'staff',
        ]);
    }

    public function test_free_tier_tenant_blocked_from_adding_customer(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'freetier', 'status' => 'free_tier', 'plan' => 'free']);
        URL::forceRootUrl('http://freetier.pawpass.com');
        $staff = $this->makeStaffFor($tenant);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->jwtFor($staff)])
            ->postJson('/api/admin/v1/customers', ['name' => 'Test Customer']);

        $response->assertStatus(403)
            ->assertJsonPath('error', 'PLAN_FEATURE_NOT_AVAILABLE');
    }

    public function test_starter_tenant_can_add_customer(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'startercust', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://startercust.pawpass.com');
        $staff = $this->makeStaffFor($tenant);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->jwtFor($staff)])
            ->postJson('/api/admin/v1/customers', ['name' => 'Allowed Customer']);

        $response->assertStatus(201);
    }

    public function test_free_tier_tenant_blocked_from_adding_dog(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'freedog', 'status' => 'free_tier', 'plan' => 'free']);
        URL::forceRootUrl('http://freedog.pawpass.com');
        $staff = $this->makeStaffFor($tenant);

        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->jwtFor($staff)])
            ->postJson('/api/admin/v1/dogs', [
                'customer_id' => $customer->id,
                'name'        => 'Buddy',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error', 'PLAN_FEATURE_NOT_AVAILABLE');
    }

    public function test_trialing_tenant_with_starter_equivalent_can_add_customer(): void
    {
        $tenant = Tenant::factory()->create([
            'slug'          => 'trialingcust',
            'status'        => 'trialing',
            'plan'          => 'starter',
            'trial_ends_at' => now()->addDays(14),
        ]);
        URL::forceRootUrl('http://trialingcust.pawpass.com');
        $staff = $this->makeStaffFor($tenant);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->jwtFor($staff)])
            ->postJson('/api/admin/v1/customers', ['name' => 'Trial Customer']);

        $response->assertStatus(201);
    }
}
