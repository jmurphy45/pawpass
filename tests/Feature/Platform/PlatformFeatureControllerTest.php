<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformFeature;
use App\Models\PlatformPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class PlatformFeatureControllerTest extends TestCase
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

    public function test_platform_admin_can_list_features(): void
    {
        PlatformFeature::factory()->count(3)->create();

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/platform/v1/features');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
        $response->assertJsonStructure(['data' => [['id', 'slug', 'name', 'description', 'is_marketing', 'sort_order']]]);
    }

    public function test_platform_admin_can_create_feature(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/platform/v1/features', [
                'slug'         => 'new_feature',
                'name'         => 'New Feature',
                'description'  => 'A brand new feature.',
                'is_marketing' => true,
                'sort_order'   => 99,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.slug', 'new_feature')
            ->assertJsonPath('data.name', 'New Feature');

        $this->assertDatabaseHas('platform_features', ['slug' => 'new_feature']);
    }

    public function test_create_feature_requires_unique_slug(): void
    {
        PlatformFeature::factory()->create(['slug' => 'existing_feature']);

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/platform/v1/features', [
                'slug' => 'existing_feature',
                'name' => 'Duplicate',
            ]);

        $response->assertStatus(422);
    }

    public function test_platform_admin_can_update_feature(): void
    {
        $feature = PlatformFeature::factory()->create(['name' => 'Old Name']);

        $response = $this->withHeaders($this->headers())
            ->patchJson("/api/platform/v1/features/{$feature->id}", [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_platform_admin_can_delete_feature(): void
    {
        $feature = PlatformFeature::factory()->create();

        $response = $this->withHeaders($this->headers())
            ->deleteJson("/api/platform/v1/features/{$feature->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('platform_features', ['id' => $feature->id]);
    }

    public function test_deleting_feature_removes_pivot_entries(): void
    {
        $feature = PlatformFeature::factory()->create(['slug' => 'deletable']);
        $plan = PlatformPlan::factory()->create();
        $plan->features()->attach($feature->id);

        $this->withHeaders($this->headers())
            ->deleteJson("/api/platform/v1/features/{$feature->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('platform_plan_features', ['feature_id' => $feature->id]);
    }

    public function test_non_platform_admin_cannot_access_features(): void
    {
        $tenant = \App\Models\Tenant::factory()->create(['status' => 'active', 'plan' => 'starter']);
        \App\Models\PlatformPlan::factory()->create(['slug' => 'starter', 'features' => []]);
        URL::forceRootUrl('http://'.$tenant->slug.'.pawpass.com');
        $staff = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'staff']);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->jwtFor($staff)])
            ->getJson('/api/platform/v1/features');

        $response->assertStatus(403);
    }
}
