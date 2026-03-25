<?php

namespace Tests\Feature\Web\Admin;

use App\Models\AddonType;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ServicesControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $staff;

    private Customer $customer;

    private Dog $dog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'services-web', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://services-web.pawpass.com');

        $this->owner    = User::factory()->businessOwner()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
        $this->staff    = User::factory()->staff()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->dog      = Dog::factory()->forCustomer($this->customer)->create();
    }

    public function test_index_renders_inertia_page(): void
    {
        $this->actingAs($this->staff);

        $response = $this->get('/admin/services');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/Services/Index'));
    }

    public function test_index_returns_addon_types(): void
    {
        AddonType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Nail Clip', 'context' => 'both']);
        AddonType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bath', 'context' => 'daycare']);

        $this->actingAs($this->staff);

        $response = $this->get('/admin/services');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Services/Index')
            ->has('addonTypes', 2)
        );
    }

    public function test_store_creates_addon_type_as_owner(): void
    {
        $this->actingAs($this->owner);

        $response = $this->post('/admin/services', [
            'name'        => 'Grooming',
            'price_cents' => 3500,
            'context'     => 'both',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('addon_types', [
            'tenant_id'   => $this->tenant->id,
            'name'        => 'Grooming',
            'price_cents' => 3500,
            'context'     => 'both',
        ]);
    }

    public function test_store_forbidden_for_staff(): void
    {
        $this->actingAs($this->staff);

        $response = $this->post('/admin/services', [
            'name'        => 'Grooming',
            'price_cents' => 3500,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('addon_types', ['name' => 'Grooming']);
    }

    public function test_update_patches_addon_type_as_owner(): void
    {
        $addon = AddonType::factory()->create(['tenant_id' => $this->tenant->id, 'price_cents' => 1000]);

        $this->actingAs($this->owner);

        $response = $this->patch("/admin/services/{$addon->id}", [
            'price_cents' => 1500,
            'context'     => 'boarding',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('addon_types', [
            'id'          => $addon->id,
            'price_cents' => 1500,
            'context'     => 'boarding',
        ]);
    }

    public function test_update_forbidden_for_staff(): void
    {
        $addon = AddonType::factory()->create(['tenant_id' => $this->tenant->id, 'price_cents' => 1000]);

        $this->actingAs($this->staff);

        $response = $this->patch("/admin/services/{$addon->id}", ['price_cents' => 1500]);

        $response->assertForbidden();
        $this->assertDatabaseHas('addon_types', ['id' => $addon->id, 'price_cents' => 1000]);
    }

    public function test_destroy_deletes_unused_addon_type(): void
    {
        $addon = AddonType::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->owner);

        $response = $this->delete("/admin/services/{$addon->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('addon_types', ['id' => $addon->id]);
    }

    public function test_destroy_forbidden_for_staff(): void
    {
        $addon = AddonType::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->staff);

        $response = $this->delete("/admin/services/{$addon->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('addon_types', ['id' => $addon->id]);
    }

    public function test_destroy_409_when_addon_type_in_use(): void
    {
        $addon = AddonType::factory()->create(['tenant_id' => $this->tenant->id]);

        // Create a reservation with this addon type to trigger the in-use guard
        $reservation = Reservation::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $this->dog->id,
            'customer_id' => $this->customer->id,
            'created_by'  => $this->owner->id,
        ]);
        \DB::table('reservation_addons')->insert([
            'reservation_id'   => $reservation->id,
            'addon_type_id'    => $addon->id,
            'quantity'         => 1,
            'unit_price_cents' => 1000,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->actingAs($this->owner);

        $response = $this->delete("/admin/services/{$addon->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('addon_types', ['id' => $addon->id]);
    }
}
