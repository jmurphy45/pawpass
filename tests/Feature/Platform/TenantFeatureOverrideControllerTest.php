<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformFeature;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class TenantFeatureOverrideControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();
        URL::forceRootUrl('http://platform.pawpass.com');
        $this->admin = User::factory()->platformAdmin()->create();

        PlatformPlan::factory()->create(['slug' => 'free', 'features' => [], 'staff_limit' => 1]);
        PlatformPlan::factory()->create(['slug' => 'starter', 'features' => [], 'staff_limit' => 5]);
    }

    protected function tearDown(): void
    {
        Feature::flushCache();
        parent::tearDown();
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->admin)];
    }

    public function test_platform_admin_can_list_tenant_feature_overrides(): void
    {
        $tenant = Tenant::factory()->create(['plan' => 'starter', 'status' => 'active']);
        PlatformFeature::factory()->create(['slug' => 'sms_notifications']);

        $response = $this->withHeaders($this->headers())
            ->getJson("/api/platform/v1/tenants/{$tenant->id}/features");

        // Returns 200 with data array (may be empty in array-driver test env)
        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_platform_admin_can_grant_feature_override_to_tenant(): void
    {
        $tenant = Tenant::factory()->create(['plan' => 'free', 'status' => 'active']);
        PlatformFeature::factory()->create(['slug' => 'sms_notifications']);

        $response = $this->withHeaders($this->headers())
            ->postJson("/api/platform/v1/tenants/{$tenant->id}/features", [
                'feature_slug' => 'sms_notifications',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.feature_slug', 'sms_notifications')
            ->assertJsonPath('data.value', true);

        // With array driver, activation is in the same process — check without flushing cache
        $this->assertTrue(Feature::for($tenant)->active('sms_notifications'));
    }

    public function test_platform_admin_can_revoke_feature_override(): void
    {
        $tenant = Tenant::factory()->create(['plan' => 'free', 'status' => 'active']);
        PlatformFeature::factory()->create(['slug' => 'sms_notifications']);

        // Grant via API
        $this->withHeaders($this->headers())
            ->postJson("/api/platform/v1/tenants/{$tenant->id}/features", [
                'feature_slug' => 'sms_notifications',
            ])->assertStatus(201);

        $this->assertTrue(Feature::for($tenant)->active('sms_notifications'));

        // Revoke via API
        $response = $this->withHeaders($this->headers())
            ->deleteJson("/api/platform/v1/tenants/{$tenant->id}/features/sms_notifications");

        $response->assertStatus(204);

        // After forgetting, falls back to resolver — free plan has no sms
        $this->assertFalse(Feature::for($tenant)->active('sms_notifications'));
    }

    public function test_granting_nonexistent_feature_returns_422(): void
    {
        $tenant = Tenant::factory()->create(['plan' => 'free', 'status' => 'active']);

        $response = $this->withHeaders($this->headers())
            ->postJson("/api/platform/v1/tenants/{$tenant->id}/features", [
                'feature_slug' => 'nonexistent_feature',
            ]);

        $response->assertStatus(422);
    }
}
