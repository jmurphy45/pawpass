<?php

namespace Tests\Feature\Platform;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class ReportControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private User $platformAdmin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->platformAdmin = User::factory()->platformAdmin()->create();

        $tenant = Tenant::factory()->create(['slug' => 'plat-rpt-tenant', 'status' => 'active']);
        $this->regularUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'business_owner',
        ]);
    }

    private function platformHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->platformAdmin)];
    }

    private function tenantHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->regularUser)];
    }

    // ============================================================
    // Platform Revenue
    // ============================================================

    public function test_platform_revenue_returns_200_for_admin(): void
    {
        $response = $this->withHeaders($this->platformHeaders())
            ->getJson('/api/platform/v1/reports/revenue');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_platform_revenue_returns_403_for_non_admin(): void
    {
        $response = $this->withHeaders($this->tenantHeaders())
            ->getJson('/api/platform/v1/reports/revenue');

        $response->assertStatus(403);
    }

    public function test_platform_revenue_caches_result(): void
    {
        Cache::flush();
        $this->assertFalse(Cache::has('platform:revenue:snapshot'));

        $this->withHeaders($this->platformHeaders())
            ->getJson('/api/platform/v1/reports/revenue');

        $this->assertTrue(Cache::has('platform:revenue:snapshot'));
    }

    // ============================================================
    // Tenant Health
    // ============================================================

    public function test_tenant_health_returns_200_for_admin(): void
    {
        $response = $this->withHeaders($this->platformHeaders())
            ->getJson('/api/platform/v1/reports/tenant-health');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_tenant_health_returns_403_for_non_admin(): void
    {
        $response = $this->withHeaders($this->tenantHeaders())
            ->getJson('/api/platform/v1/reports/tenant-health');

        $response->assertStatus(403);
    }

    public function test_tenant_health_returns_tenant_rows(): void
    {
        Tenant::factory()->create(['slug' => 'health-t1', 'status' => 'active', 'plan' => 'starter']);

        $response = $this->withHeaders($this->platformHeaders())
            ->getJson('/api/platform/v1/reports/tenant-health');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $row = $data[0];
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayHasKey('status', $row);
        $this->assertArrayHasKey('plan', $row);
        $this->assertArrayHasKey('dogs', $row);
        $this->assertArrayHasKey('customers', $row);
        $this->assertArrayHasKey('orders_30_days', $row);
    }

    public function test_tenant_health_caches_result(): void
    {
        Cache::flush();
        $this->assertFalse(Cache::has('platform:tenant_health:snapshot'));

        $this->withHeaders($this->platformHeaders())
            ->getJson('/api/platform/v1/reports/tenant-health');

        $this->assertTrue(Cache::has('platform:tenant_health:snapshot'));
    }

    // ============================================================
    // Notification Delivery
    // ============================================================

    public function test_notification_delivery_returns_200_for_admin(): void
    {
        $response = $this->withHeaders($this->platformHeaders())
            ->getJson('/api/platform/v1/reports/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_notification_delivery_returns_403_for_non_admin(): void
    {
        $response = $this->withHeaders($this->tenantHeaders())
            ->getJson('/api/platform/v1/reports/notifications');

        $response->assertStatus(403);
    }

    public function test_notification_delivery_accepts_tenant_filter(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'nd-filter-t', 'status' => 'active']);
        $user   = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'staff']);

        \Illuminate\Support\Facades\DB::table('notification_logs')->insert([
            'tenant_id'  => $tenant->id,
            'user_id'    => $user->id,
            'type'       => 'test',
            'channel'    => 'email',
            'status'     => 'sent',
            'created_at' => now()->toDateTimeString(),
        ]);

        $response = $this->withHeaders($this->platformHeaders())
            ->getJson("/api/platform/v1/reports/notifications?tenant_id={$tenant->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals(1, $data[0]['count']);
    }
}
