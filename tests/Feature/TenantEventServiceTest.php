<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantEvent;
use App\Services\TenantEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantEventServiceTest extends TestCase
{
    use RefreshDatabase;

    private TenantEventService $service;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TenantEventService::class);
        $this->tenant = Tenant::factory()->create();
    }

    public function test_record_inserts_event_row(): void
    {
        $this->service->record($this->tenant->id, 'plan_upgraded', ['from' => 'free', 'to' => 'starter']);

        $this->assertDatabaseHas('tenant_events', [
            'tenant_id' => $this->tenant->id,
            'event_type' => 'plan_upgraded',
        ]);
        $this->assertDatabaseCount('tenant_events', 1);
    }

    public function test_record_called_twice_inserts_two_rows(): void
    {
        $this->service->record($this->tenant->id, 'plan_upgraded');
        $this->service->record($this->tenant->id, 'plan_upgraded');

        $this->assertDatabaseCount('tenant_events', 2);
    }

    public function test_record_once_inserts_only_on_first_call(): void
    {
        $this->service->recordOnce($this->tenant->id, 'first_checkin');
        $this->service->recordOnce($this->tenant->id, 'first_checkin');

        $this->assertDatabaseCount('tenant_events', 1);
    }

    public function test_record_once_does_not_cross_contaminate_tenants(): void
    {
        $tenantB = Tenant::factory()->create();

        $this->service->recordOnce($this->tenant->id, 'first_checkin');
        $this->service->recordOnce($tenantB->id, 'first_checkin');

        $this->assertDatabaseCount('tenant_events', 2);
        $this->assertDatabaseHas('tenant_events', ['tenant_id' => $this->tenant->id, 'event_type' => 'first_checkin']);
        $this->assertDatabaseHas('tenant_events', ['tenant_id' => $tenantB->id, 'event_type' => 'first_checkin']);
    }

    public function test_record_stores_payload_as_jsonb(): void
    {
        $this->service->record($this->tenant->id, 'plan_upgraded', ['from' => 'free', 'to' => 'pro']);

        $event = TenantEvent::first();
        $this->assertSame('free', $event->payload['from']);
        $this->assertSame('pro', $event->payload['to']);
    }

    public function test_record_with_empty_payload_stores_null(): void
    {
        $this->service->record($this->tenant->id, 'first_checkin');

        $event = TenantEvent::first();
        $this->assertNull($event->payload);
    }
}
