<?php

namespace Tests\Feature\Platform;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class TenantControllerTest extends TestCase
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

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_all_tenants(): void
    {
        Tenant::factory()->count(3)->create();

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/platform/v1/tenants');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_filters_by_status(): void
    {
        Tenant::factory()->create(['status' => 'active']);
        Tenant::factory()->create(['status' => 'suspended']);
        Tenant::factory()->create(['status' => 'suspended']);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/platform/v1/tenants?status=suspended');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        $this->assertSame('suspended', $response->json('data.0.status'));
    }

    public function test_index_returns_tenant_fields(): void
    {
        Tenant::factory()->create(['name' => 'Happy Paws', 'slug' => 'happy-paws-001', 'status' => 'active']);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/platform/v1/tenants');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Happy Paws')
            ->assertJsonPath('data.0.slug', 'happy-paws-001')
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'status', 'platform_fee_pct', 'payout_schedule']]]);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_full_detail_with_owner_and_user_count(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'business_owner', 'name' => 'Jane Owner']);
        $tenant->update(['owner_user_id' => $owner->id]);
        User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'staff']);

        $response = $this->withHeaders($this->headers())
            ->getJson("/api/platform/v1/tenants/{$tenant->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $tenant->id)
            ->assertJsonPath('data.owner.name', 'Jane Owner')
            ->assertJsonPath('data.user_count', 2);
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        $this->withHeaders($this->headers())
            ->getJson('/api/platform/v1/tenants/01ZZZZZZZZZZZZZZZZZZZZZZZ')
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_platform_fee_and_payout_schedule(): void
    {
        $tenant = Tenant::factory()->create(['platform_fee_pct' => '5.00', 'payout_schedule' => 'monthly']);

        $response = $this->withHeaders($this->headers())
            ->patchJson("/api/platform/v1/tenants/{$tenant->id}", [
                'platform_fee_pct' => 7.5,
                'payout_schedule' => 'weekly',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.payout_schedule', 'weekly');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'payout_schedule' => 'weekly',
        ]);
    }

    public function test_update_rejects_negative_platform_fee(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withHeaders($this->headers())
            ->patchJson("/api/platform/v1/tenants/{$tenant->id}", ['platform_fee_pct' => -1])
            ->assertStatus(422);
    }

    public function test_update_rejects_fee_over_100(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withHeaders($this->headers())
            ->patchJson("/api/platform/v1/tenants/{$tenant->id}", ['platform_fee_pct' => 101])
            ->assertStatus(422);
    }

    public function test_update_rejects_invalid_payout_schedule(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withHeaders($this->headers())
            ->patchJson("/api/platform/v1/tenants/{$tenant->id}", ['payout_schedule' => 'quarterly'])
            ->assertStatus(422);
    }

    public function test_update_returns_404_for_unknown_id(): void
    {
        $this->withHeaders($this->headers())
            ->patchJson('/api/platform/v1/tenants/01ZZZZZZZZZZZZZZZZZZZZZZZ', ['payout_schedule' => 'daily'])
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Status transitions
    // -------------------------------------------------------------------------

    public function test_suspend_sets_status_suspended_and_creates_audit_log(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        $response = $this->withHeaders($this->headers())
            ->postJson("/api/platform/v1/tenants/{$tenant->id}/suspend", ['reason' => 'Policy violation']);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'suspended');

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'suspended']);
        $this->assertDatabaseHas('platform_audit_log', [
            'actor_id' => $this->admin->id,
            'actor_role' => 'platform_admin',
            'action' => 'tenant.suspended',
            'target_type' => 'tenant',
            'target_id' => $tenant->id,
        ]);
    }

    public function test_reinstate_sets_status_active(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'suspended']);

        $this->withHeaders($this->headers())
            ->postJson("/api/platform/v1/tenants/{$tenant->id}/reinstate")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'active']);
        $this->assertDatabaseHas('platform_audit_log', ['action' => 'tenant.reinstated', 'target_id' => $tenant->id]);
    }

    public function test_cancel_sets_status_cancelled(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        $this->withHeaders($this->headers())
            ->postJson("/api/platform/v1/tenants/{$tenant->id}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('platform_audit_log', ['action' => 'tenant.cancelled', 'target_id' => $tenant->id]);
    }

    public function test_suspend_returns_422_if_already_suspended(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'suspended']);

        $this->withHeaders($this->headers())
            ->postJson("/api/platform/v1/tenants/{$tenant->id}/suspend")
            ->assertStatus(422);
    }

    public function test_suspend_returns_404_for_unknown_id(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/api/platform/v1/tenants/01ZZZZZZZZZZZZZZZZZZZZZZZ/suspend')
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_non_platform_admin_gets_403(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'business_owner']);

        $this->withHeaders(['Authorization' => 'Bearer '.$this->jwtFor($owner)])
            ->getJson('/api/platform/v1/tenants')
            ->assertStatus(403);
    }
}
