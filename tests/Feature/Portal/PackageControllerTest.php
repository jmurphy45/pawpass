<?php

namespace Tests\Feature\Portal;

use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class PackageControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'pkgtest', 'status' => 'active']);
        URL::forceRootUrl('http://pkgtest.pawpass.com');

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'customer',
        ]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->user)];
    }

    public function test_returns_active_packages_only(): void
    {
        Package::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true, 'name' => 'Active Pack']);
        Package::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false, 'name' => 'Inactive Pack']);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/packages');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Active Pack', $response->json('data.0.name'));
    }

    public function test_returns_expected_package_fields(): void
    {
        Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'name' => 'Day Pack 10',
            'type' => 'one_time',
            'price' => '50.00',
            'credit_count' => 10,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/packages');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name', 'description', 'type', 'price', 'credit_count', 'dog_limit', 'duration_days', 'is_active']]]);
    }

    public function test_unlimited_package_is_visible_when_active(): void
    {
        Package::factory()->unlimited(30)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/packages');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('unlimited', $response->json('data.0.type'));
        $this->assertEquals(30, $response->json('data.0.duration_days'));
    }

    public function test_does_not_return_packages_from_other_tenants(): void
    {
        $other = Tenant::factory()->create(['slug' => 'otherpkg', 'status' => 'active']);
        Package::factory()->create(['tenant_id' => $other->id, 'is_active' => true]);
        Package::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/packages');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/portal/v1/packages');

        $response->assertStatus(401);
    }
}
