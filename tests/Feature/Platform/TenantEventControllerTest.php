<?php

namespace Tests\Feature\Platform;

use App\Models\Tenant;
use App\Models\TenantEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class TenantEventControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private User $admin;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        URL::forceRootUrl('http://platform.pawpass.com');

        $this->admin = User::factory()->platformAdmin()->create();
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->admin)];
    }

    public function test_platform_admin_can_list_tenant_events(): void
    {
        TenantEvent::factory()->forEvent('first_checkin')->create(['tenant_id' => $this->tenantA->id]);
        TenantEvent::factory()->forEvent('first_purchase')->create(['tenant_id' => $this->tenantB->id]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/platform/v1/tenant-events');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'tenant_id', 'event_type', 'payload', 'created_at']],
                'meta' => ['total', 'current_page', 'last_page'],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_filter_by_tenant_id(): void
    {
        TenantEvent::factory()->forEvent('first_checkin')->create(['tenant_id' => $this->tenantA->id]);
        TenantEvent::factory()->forEvent('first_purchase')->create(['tenant_id' => $this->tenantB->id]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/platform/v1/tenant-events?tenant_id='.$this->tenantA->id);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($this->tenantA->id, $response->json('data.0.tenant_id'));
    }

    public function test_can_filter_by_event_type(): void
    {
        TenantEvent::factory()->forEvent('first_checkin')->create(['tenant_id' => $this->tenantA->id]);
        TenantEvent::factory()->forEvent('first_purchase')->create(['tenant_id' => $this->tenantA->id]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/platform/v1/tenant-events?event_type=first_checkin');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('first_checkin', $response->json('data.0.event_type'));
    }

    public function test_non_platform_admin_cannot_access(): void
    {
        $owner = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'business_owner',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->jwtFor($owner)])
            ->getJson('/api/platform/v1/tenant-events');

        $response->assertStatus(403);
    }
}
