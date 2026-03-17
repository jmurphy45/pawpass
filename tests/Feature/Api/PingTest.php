<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class PingTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();
    }

    private function withHost(string $host): static
    {
        \Illuminate\Support\Facades\URL::forceRootUrl("http://{$host}");

        return $this;
    }

    public function test_ping_returns_pong_with_valid_jwt_and_tenant(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'testpaws', 'status' => 'active']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'customer']);
        $token = $this->jwtFor($user);

        $response = $this->withHost('testpaws.pawpass.com')
            ->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/portal/v1/ping');

        $response->assertStatus(200)
            ->assertJson(['data' => 'pong']);
    }

    public function test_ping_returns_401_without_token(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'testpaws2', 'status' => 'active']);

        $response = $this->withHost('testpaws2.pawpass.com')
            ->getJson('/api/portal/v1/ping');

        $response->assertStatus(401);
    }

    public function test_admin_ping_returns_403_for_customer_role(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'admintest', 'status' => 'active']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'customer']);
        $token = $this->jwtFor($user);

        $response = $this->withHost('admintest.pawpass.com')
            ->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/admin/v1/ping');

        $response->assertStatus(403);
    }

    public function test_admin_ping_returns_200_for_staff(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'stafftest', 'status' => 'active']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'staff']);
        $token = $this->jwtFor($user);

        $response = $this->withHost('stafftest.pawpass.com')
            ->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/admin/v1/ping');

        $response->assertStatus(200);
    }

    public function test_platform_ping_returns_200_for_platform_admin(): void
    {
        $user = User::factory()->platformAdmin()->create();
        $token = $this->jwtFor($user);

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/platform/v1/ping');

        $response->assertStatus(200);
    }

    public function test_platform_ping_returns_403_for_non_platform_admin(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'plattest', 'status' => 'active']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'business_owner']);
        $token = $this->jwtFor($user);

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/platform/v1/ping');

        $response->assertStatus(403);
    }
}
