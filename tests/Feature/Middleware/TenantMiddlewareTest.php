<?php

namespace Tests\Feature\Middleware;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class TenantMiddlewareTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        // Register a test route with tenant middleware
        Route::middleware('tenant')
            ->get('/_test/tenant-check', fn () => response()->json(['data' => app('current.tenant.id')]));
    }

    private function withHost(string $host): static
    {
        \Illuminate\Support\Facades\URL::forceRootUrl("http://{$host}");

        return $this;
    }

    public function test_active_tenant_resolves_from_subdomain(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'happypaws', 'status' => 'active']);

        $response = $this->withHost('happypaws.pawpass.com')
            ->getJson('/_test/tenant-check');

        $response->assertStatus(200)
            ->assertJson(['data' => $tenant->id]);
    }

    public function test_unknown_slug_returns_404(): void
    {
        $response = $this->withHost('nonexistent.pawpass.com')
            ->getJson('/_test/tenant-check');

        $response->assertStatus(404);
    }

    public function test_suspended_tenant_returns_403(): void
    {
        Tenant::factory()->create(['slug' => 'suspended-paws', 'status' => 'suspended']);

        $response = $this->withHost('suspended-paws.pawpass.com')
            ->getJson('/_test/tenant-check');

        $response->assertStatus(403);
    }

    public function test_inactive_tenant_returns_404(): void
    {
        Tenant::factory()->create(['slug' => 'pending-paws', 'status' => 'pending_verification']);

        $response = $this->withHost('pending-paws.pawpass.com')
            ->getJson('/_test/tenant-check');

        $response->assertStatus(404);
    }
}
