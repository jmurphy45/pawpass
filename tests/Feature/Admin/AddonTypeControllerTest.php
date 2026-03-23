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

class AddonTypeControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'addon-test', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://addon-test.pawpass.com');

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

    public function test_index_returns_addon_types_for_tenant(): void
    {
        AddonType::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $other = Tenant::factory()->create(['slug' => 'other-addon', 'status' => 'active']);
        AddonType::factory()->create(['tenant_id' => $other->id]);

        $response = $this->withHeaders($this->staffHeaders())->getJson('/api/admin/v1/addon-types');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_staff_cannot_create_addon_type(): void
    {
        $response = $this->withHeaders($this->staffHeaders())->postJson('/api/admin/v1/addon-types', [
            'name' => 'Extra Walk', 'price_cents' => 1500,
        ]);

        $response->assertStatus(403);
    }

    public function test_owner_can_create_addon_type(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())->postJson('/api/admin/v1/addon-types', [
            'name' => 'Extra Walk', 'price_cents' => 1500,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Extra Walk');
        $response->assertJsonPath('data.price_cents', 1500);
    }

    public function test_owner_can_update_addon_type(): void
    {
        $addon = AddonType::factory()->create(['tenant_id' => $this->tenant->id, 'price_cents' => 1000]);

        $response = $this->withHeaders($this->ownerHeaders())->patchJson("/api/admin/v1/addon-types/{$addon->id}", [
            'price_cents' => 1500,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.price_cents', 1500);
    }

    public function test_owner_can_deactivate_addon_type(): void
    {
        $addon = AddonType::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);

        $response = $this->withHeaders($this->ownerHeaders())->patchJson("/api/admin/v1/addon-types/{$addon->id}", [
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_active', false);
    }

    public function test_owner_cannot_delete_addon_type_in_use(): void
    {
        $addon = AddonType::factory()->create(['tenant_id' => $this->tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $dog->id,
            'customer_id' => $customer->id, 'created_by' => $this->owner->id,
        ]);
        ReservationAddon::create([
            'reservation_id'   => $reservation->id,
            'addon_type_id'    => $addon->id,
            'quantity'         => 1,
            'unit_price_cents' => $addon->price_cents,
        ]);

        $response = $this->withHeaders($this->ownerHeaders())->deleteJson("/api/admin/v1/addon-types/{$addon->id}");

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'ADDON_TYPE_IN_USE');
    }

    public function test_owner_can_delete_unused_addon_type(): void
    {
        $addon = AddonType::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->ownerHeaders())->deleteJson("/api/admin/v1/addon-types/{$addon->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('addon_types', ['id' => $addon->id]);
    }

    public function test_cross_tenant_addon_type_not_accessible(): void
    {
        $other = Tenant::factory()->create(['slug' => 'other-addon2', 'status' => 'active']);
        $addon = AddonType::factory()->create(['tenant_id' => $other->id]);

        $response = $this->withHeaders($this->ownerHeaders())->patchJson("/api/admin/v1/addon-types/{$addon->id}", [
            'name' => 'Hack',
        ]);

        $response->assertStatus(404);
    }
}
