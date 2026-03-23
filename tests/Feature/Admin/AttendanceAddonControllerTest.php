<?php

namespace Tests\Feature\Admin;

use App\Models\AddonType;
use App\Models\Attendance;
use App\Models\AttendanceAddon;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class AttendanceAddonControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Attendance $attendance;

    private AddonType $addonType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'attaddon-test', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://attaddon-test.pawpass.com');

        $this->staff    = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff']);
        $customer       = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog            = Dog::factory()->forCustomer($customer)->create();

        $this->attendance = Attendance::create([
            'tenant_id'      => $this->tenant->id,
            'dog_id'         => $dog->id,
            'checked_in_by'  => $this->staff->id,
            'checked_in_at'  => now(),
        ]);

        $this->addonType = AddonType::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'price_cents' => 1500,
            'context'     => 'daycare',
        ]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];
    }

    public function test_index_returns_addons_for_attendance(): void
    {
        AttendanceAddon::create([
            'attendance_id'    => $this->attendance->id,
            'addon_type_id'    => $this->addonType->id,
            'quantity'         => 1,
            'unit_price_cents' => 1500,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/admin/v1/attendances/{$this->attendance->id}/addons");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertNotNull($response->json('data.0.addon_name'));
    }

    public function test_store_creates_addon_with_price_snapshot(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson(
            "/api/admin/v1/attendances/{$this->attendance->id}/addons",
            ['addon_type_id' => $this->addonType->id]
        );

        $response->assertStatus(201);
        $response->assertJsonPath('data.unit_price_cents', 1500);
        $response->assertJsonPath('data.quantity', 1);
    }

    public function test_store_rejects_boarding_only_addon_type(): void
    {
        $boardingOnly = AddonType::factory()->create([
            'tenant_id' => $this->tenant->id,
            'context'   => 'boarding',
        ]);

        $response = $this->withHeaders($this->authHeaders())->postJson(
            "/api/admin/v1/attendances/{$this->attendance->id}/addons",
            ['addon_type_id' => $boardingOnly->id]
        );

        $response->assertStatus(404);
    }

    public function test_store_accepts_both_context_addon(): void
    {
        $bothAddon = AddonType::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'price_cents' => 2000,
            'context'     => 'both',
        ]);

        $response = $this->withHeaders($this->authHeaders())->postJson(
            "/api/admin/v1/attendances/{$this->attendance->id}/addons",
            ['addon_type_id' => $bothAddon->id]
        );

        $response->assertStatus(201);
        $response->assertJsonPath('data.unit_price_cents', 2000);
    }

    public function test_price_snapshot_unaffected_by_later_price_change(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson(
            "/api/admin/v1/attendances/{$this->attendance->id}/addons",
            ['addon_type_id' => $this->addonType->id]
        );

        $addonId = $response->json('data.id');
        $this->addonType->update(['price_cents' => 9999]);

        $addon = AttendanceAddon::find($addonId);
        $this->assertEquals(1500, $addon->unit_price_cents);
    }

    public function test_store_rejects_unknown_addon_type(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson(
            "/api/admin/v1/attendances/{$this->attendance->id}/addons",
            ['addon_type_id' => str_repeat('x', 26)]
        );

        $response->assertStatus(404);
    }

    public function test_destroy_removes_addon(): void
    {
        $addon = AttendanceAddon::create([
            'attendance_id' => $this->attendance->id, 'addon_type_id' => $this->addonType->id,
            'quantity' => 1, 'unit_price_cents' => 1500,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/admin/v1/attendances/{$this->attendance->id}/addons/{$addon->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('attendance_addons', ['id' => $addon->id]);
    }

    public function test_destroy_rejects_wrong_attendance(): void
    {
        $customer  = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog       = Dog::factory()->forCustomer($customer)->create();
        $otherAtt  = Attendance::create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id, 'checked_in_at' => now(),
        ]);
        $addon = AttendanceAddon::create([
            'attendance_id' => $otherAtt->id, 'addon_type_id' => $this->addonType->id,
            'quantity' => 1, 'unit_price_cents' => 1500,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/admin/v1/attendances/{$this->attendance->id}/addons/{$addon->id}");

        $response->assertStatus(404);
    }
}
