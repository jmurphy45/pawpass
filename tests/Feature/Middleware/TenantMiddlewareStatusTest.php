<?php

namespace Tests\Feature\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class TenantMiddlewareStatusTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();
    }

    private function pingAs(Tenant $tenant): \Illuminate\Testing\TestResponse
    {
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'staff',
        ]);

        URL::forceRootUrl("http://{$tenant->slug}.pawpass.com");

        return $this->withHeaders(['Authorization' => 'Bearer '.$this->jwtFor($user)])
            ->getJson('/api/admin/v1/ping');
    }

    public function test_active_tenant_allowed(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'mw-active', 'status' => 'active']);

        $this->pingAs($tenant)->assertStatus(200);
    }

    public function test_trialing_tenant_allowed(): void
    {
        $tenant = Tenant::factory()->create([
            'slug'          => 'mw-trialing',
            'status'        => 'trialing',
            'trial_ends_at' => now()->addDays(10),
        ]);

        $this->pingAs($tenant)->assertStatus(200);
    }

    public function test_free_tier_tenant_allowed(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'mw-freetier', 'status' => 'free_tier']);

        $this->pingAs($tenant)->assertStatus(200);
    }

    public function test_past_due_tenant_allowed(): void
    {
        $tenant = Tenant::factory()->create([
            'slug'                => 'mw-pastdue',
            'status'              => 'past_due',
            'plan_past_due_since' => now()->subDays(5),
        ]);

        $this->pingAs($tenant)->assertStatus(200);
    }

    public function test_suspended_tenant_returns_403(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'mw-suspended', 'status' => 'suspended']);
        URL::forceRootUrl('http://mw-suspended.pawpass.com');

        $this->getJson('/api/admin/v1/ping')->assertStatus(403);
    }

    public function test_cancelled_tenant_returns_404(): void
    {
        Tenant::factory()->create(['slug' => 'mw-cancelled', 'status' => 'cancelled']);
        URL::forceRootUrl('http://mw-cancelled.pawpass.com');

        $this->getJson('/api/admin/v1/ping')->assertStatus(404);
    }
}
