<?php

namespace Tests\Feature\Admin;

use App\Models\KennelUnit;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class KennelUnitControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'kennelunit-test', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://kennelunit-test.pawpass.com');

        $this->staff = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff']);
        $this->owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'business_owner']);
    }

    private function staffHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];
    }

    private function ownerHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->owner)];
    }

    public function test_index_returns_units_for_tenant_ordered_by_sort_order(): void
    {
        KennelUnit::factory()->create(['tenant_id' => $this->tenant->id, 'sort_order' => 30, 'name' => 'C Room']);
        KennelUnit::factory()->create(['tenant_id' => $this->tenant->id, 'sort_order' => 10, 'name' => 'A Room']);
        KennelUnit::factory()->create(['tenant_id' => $this->tenant->id, 'sort_order' => 20, 'name' => 'B Room']);

        $response = $this->withHeaders($this->staffHeaders())->getJson('/api/admin/v1/kennel-units');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(3, $data);
        $this->assertEquals('A Room', $data[0]['name']);
        $this->assertEquals('B Room', $data[1]['name']);
        $this->assertEquals('C Room', $data[2]['name']);
    }

    public function test_index_does_not_return_other_tenant_units(): void
    {
        KennelUnit::factory()->create(['tenant_id' => $this->tenant->id]);

        $other = Tenant::factory()->create(['slug' => 'other-ku-test', 'status' => 'active']);
        KennelUnit::factory()->count(3)->create(['tenant_id' => $other->id]);

        $response = $this->withHeaders($this->staffHeaders())->getJson('/api/admin/v1/kennel-units');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_staff_can_read_units(): void
    {
        KennelUnit::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->staffHeaders())->getJson('/api/admin/v1/kennel-units');

        $response->assertStatus(200);
    }

    public function test_owner_can_create_unit(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())->postJson('/api/admin/v1/kennel-units', [
            'name' => 'Suite 1',
            'type' => 'suite',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Suite 1');
        $response->assertJsonPath('data.type', 'suite');
        $this->assertDatabaseHas('kennel_units', ['name' => 'Suite 1', 'tenant_id' => $this->tenant->id]);
    }

    public function test_staff_cannot_create_unit(): void
    {
        $response = $this->withHeaders($this->staffHeaders())->postJson('/api/admin/v1/kennel-units', [
            'name' => 'Suite 1',
            'type' => 'standard',
        ]);

        $response->assertStatus(403);
    }

    public function test_owner_can_update_unit(): void
    {
        $unit = KennelUnit::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Old Name', 'is_active' => true]);

        $response = $this->withHeaders($this->ownerHeaders())->patchJson("/api/admin/v1/kennel-units/{$unit->id}", [
            'name'      => 'New Name',
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'New Name');
        $response->assertJsonPath('data.is_active', false);
    }

    public function test_owner_can_delete_unit_with_no_active_reservations(): void
    {
        $unit = KennelUnit::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->ownerHeaders())->deleteJson("/api/admin/v1/kennel-units/{$unit->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('kennel_units', ['id' => $unit->id]);
    }

    public function test_destroy_blocked_when_active_reservations_exist(): void
    {
        $unit = KennelUnit::factory()->create(['tenant_id' => $this->tenant->id]);
        Reservation::factory()->withUnit($unit)->confirmed()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->ownerHeaders())->deleteJson("/api/admin/v1/kennel-units/{$unit->id}");

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'UNIT_HAS_ACTIVE_RESERVATIONS');
        $this->assertDatabaseHas('kennel_units', ['id' => $unit->id]);
    }

    public function test_inactive_units_still_returned_in_index(): void
    {
        KennelUnit::factory()->inactive()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->staffHeaders())->getJson('/api/admin/v1/kennel-units');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertFalse($response->json('data.0.is_active'));
    }
}
