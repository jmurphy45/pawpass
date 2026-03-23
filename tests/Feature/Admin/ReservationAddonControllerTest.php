<?php

namespace Tests\Feature\Admin;

use App\Models\AddonType;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Reservation;
use App\Models\ReservationAddon;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class ReservationAddonControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Reservation $reservation;

    private AddonType $addonType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'resaddon-test', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://resaddon-test.pawpass.com');

        $this->staff = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();

        $this->reservation = Reservation::factory()->confirmed()->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $dog->id,
            'customer_id' => $customer->id,
            'created_by'  => $this->staff->id,
        ]);

        $this->addonType = AddonType::factory()->create(['tenant_id' => $this->tenant->id, 'price_cents' => 2000]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];
    }

    public function test_index_returns_addons_for_reservation(): void
    {
        ReservationAddon::create([
            'reservation_id' => $this->reservation->id, 'addon_type_id' => $this->addonType->id,
            'quantity' => 1, 'unit_price_cents' => 2000,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/admin/v1/reservations/{$this->reservation->id}/addons");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertNotNull($response->json('data.0.addon_name'));
    }

    public function test_store_creates_addon_with_price_snapshot(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson(
            "/api/admin/v1/reservations/{$this->reservation->id}/addons",
            ['addon_type_id' => $this->addonType->id]
        );

        $response->assertStatus(201);
        $response->assertJsonPath('data.unit_price_cents', 2000);
        $response->assertJsonPath('data.quantity', 1);
    }

    public function test_price_snapshot_unaffected_by_later_price_change(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson(
            "/api/admin/v1/reservations/{$this->reservation->id}/addons",
            ['addon_type_id' => $this->addonType->id]
        );

        $addonId = $response->json('data.id');

        // Owner changes the price after the fact
        $this->addonType->update(['price_cents' => 9999]);

        $addon = ReservationAddon::find($addonId);
        $this->assertEquals(2000, $addon->unit_price_cents);
    }

    public function test_store_defaults_quantity_to_1(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson(
            "/api/admin/v1/reservations/{$this->reservation->id}/addons",
            ['addon_type_id' => $this->addonType->id]
        );

        $response->assertStatus(201);
        $response->assertJsonPath('data.quantity', 1);
    }

    public function test_store_blocked_for_cancelled_reservation(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();
        $cancelled = Reservation::factory()->cancelled()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $dog->id,
            'customer_id' => $customer->id, 'created_by' => $this->staff->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())->postJson(
            "/api/admin/v1/reservations/{$cancelled->id}/addons",
            ['addon_type_id' => $this->addonType->id]
        );

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'RESERVATION_CANCELLED');
    }

    public function test_store_rejects_unknown_addon_type(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson(
            "/api/admin/v1/reservations/{$this->reservation->id}/addons",
            ['addon_type_id' => str_repeat('x', 26)]
        );

        $response->assertStatus(404);
    }

    public function test_destroy_removes_addon(): void
    {
        $addon = ReservationAddon::create([
            'reservation_id' => $this->reservation->id, 'addon_type_id' => $this->addonType->id,
            'quantity' => 1, 'unit_price_cents' => 2000,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/admin/v1/reservations/{$this->reservation->id}/addons/{$addon->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('reservation_addons', ['id' => $addon->id]);
    }

    public function test_store_rejects_daycare_only_addon_type(): void
    {
        $daycareOnly = AddonType::factory()->create([
            'tenant_id' => $this->tenant->id,
            'context'   => 'daycare',
        ]);

        $response = $this->withHeaders($this->authHeaders())->postJson(
            "/api/admin/v1/reservations/{$this->reservation->id}/addons",
            ['addon_type_id' => $daycareOnly->id]
        );

        $response->assertStatus(404);
    }

    public function test_destroy_rejects_wrong_reservation(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();
        $otherRes = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $dog->id,
            'customer_id' => $customer->id, 'created_by' => $this->staff->id,
        ]);
        $addon = ReservationAddon::create([
            'reservation_id' => $otherRes->id, 'addon_type_id' => $this->addonType->id,
            'quantity' => 1, 'unit_price_cents' => 2000,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/admin/v1/reservations/{$this->reservation->id}/addons/{$addon->id}");

        $response->assertStatus(404);
    }
}
