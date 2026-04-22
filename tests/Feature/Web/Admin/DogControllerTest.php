<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\DogVaccination;
use App\Models\Package;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DogControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        PlatformPlan::factory()->create(['slug' => 'starter', 'features' => ['add_dogs', 'vaccination_management']]);
        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);
    }

    public function test_index_scoped_to_tenant(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Dog::factory()->forCustomer($customer)->create(['name' => 'Buddy']);

        $otherTenant = Tenant::factory()->create(['slug' => 'other', 'status' => 'active', 'plan' => 'starter']);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        Dog::factory()->forCustomer($otherCustomer)->create(['name' => 'Rex']);

        $this->actingAs($this->staff);

        $response = $this->get('/admin/dogs');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Dogs/Index')
            ->where('dogs.total', 1)
        );
    }

    public function test_show_includes_credit_ledger_entries(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();

        $this->actingAs($this->staff);

        $response = $this->get("/admin/dogs/{$dog->id}");

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Dogs/Show')
            ->where('dog.id', $dog->id)
            ->has('ledger')
            ->has('attendance')
        );
    }

    public function test_create_page_renders(): void
    {
        $this->actingAs($this->staff);

        $response = $this->get('/admin/dogs/create');

        $response->assertInertia(fn ($page) => $page->component('Admin/Dogs/Create')->has('customers'));
    }

    public function test_store_creates_dog(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->staff);

        $response = $this->post('/admin/dogs', [
            'customer_id' => $customer->id,
            'name' => 'Buddy',
            'breed' => 'Labrador',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dogs', ['name' => 'Buddy', 'tenant_id' => $this->tenant->id]);
    }

    public function test_update_modifies_dog(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['name' => 'Old Name']);

        $this->actingAs($this->staff);

        $response = $this->patch("/admin/dogs/{$dog->id}", [
            'name' => 'New Name',
            'breed' => 'Poodle',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.dogs.show', $dog));
        $this->assertDatabaseHas('dogs', ['id' => $dog->id, 'name' => 'New Name']);
    }

    public function test_update_saves_auto_replenish_fields(): void
    {
        $package = Package::factory()->autoReplenish()->create(['tenant_id' => $this->tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['auto_replenish_enabled' => false]);

        $this->actingAs($this->staff);

        $response = $this->patch("/admin/dogs/{$dog->id}", [
            'name' => $dog->name,
            'auto_replenish_enabled' => true,
            'auto_replenish_package_id' => $package->id,
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.dogs.show', $dog));
        $this->assertDatabaseHas('dogs', [
            'id' => $dog->id,
            'auto_replenish_enabled' => true,
            'auto_replenish_package_id' => $package->id,
        ]);
    }

    public function test_update_clears_package_when_auto_replenish_disabled(): void
    {
        $package = Package::factory()->autoReplenish()->create(['tenant_id' => $this->tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create([
            'auto_replenish_enabled' => true,
            'auto_replenish_package_id' => $package->id,
        ]);

        $this->actingAs($this->staff);

        $this->patch("/admin/dogs/{$dog->id}", [
            'name' => $dog->name,
            'auto_replenish_enabled' => false,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('dogs', [
            'id' => $dog->id,
            'auto_replenish_enabled' => false,
            'auto_replenish_package_id' => null,
        ]);
    }

    public function test_edit_page_includes_eligible_packages(): void
    {
        Package::factory()->autoReplenish()->create(['tenant_id' => $this->tenant->id, 'name' => 'Eligible Pack']);
        Package::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Not Eligible']);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();

        $this->actingAs($this->staff);

        $response = $this->get("/admin/dogs/{$dog->id}/edit");

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Dogs/Edit')
            ->has('eligiblePackages', 1)
            ->where('eligiblePackages.0.name', 'Eligible Pack')
        );
    }

    public function test_cross_tenant_dog_returns_404(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other', 'status' => 'active', 'plan' => 'starter']);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherDog = Dog::factory()->forCustomer($otherCustomer)->create();

        $this->actingAs($this->staff);

        $response = $this->get("/admin/dogs/{$otherDog->id}");

        $response->assertStatus(404);
    }

    public function test_show_includes_vaccinations(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();
        DogVaccination::factory()->create(['tenant_id' => $this->tenant->id, 'dog_id' => $dog->id, 'vaccine_name' => 'Rabies']);

        $this->actingAs($this->staff);

        $response = $this->get("/admin/dogs/{$dog->id}");

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Dogs/Show')
            ->has('vaccinations', 1)
            ->where('vaccinations.0.vaccine_name', 'Rabies')
        );
    }

    public function test_store_vaccination_creates_record(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();

        $this->actingAs($this->staff);

        $response = $this->post("/admin/dogs/{$dog->id}/vaccinations", [
            'vaccine_name' => 'Bordetella',
            'administered_at' => '2026-01-15',
            'expires_at' => '2027-01-15',
        ]);

        $response->assertRedirect(route('admin.dogs.show', $dog));
        $this->assertDatabaseHas('dog_vaccinations', [
            'dog_id' => $dog->id,
            'tenant_id' => $this->tenant->id,
            'vaccine_name' => 'Bordetella',
        ]);
    }

    public function test_store_vaccination_validates_required_fields(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();

        $this->actingAs($this->staff);

        $response = $this->post("/admin/dogs/{$dog->id}/vaccinations", []);

        $response->assertSessionHasErrors(['vaccine_name', 'administered_at']);
    }

    public function test_destroy_vaccination_deletes_record(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();
        $vaccination = DogVaccination::factory()->create(['tenant_id' => $this->tenant->id, 'dog_id' => $dog->id]);

        $this->actingAs($this->staff);

        $response = $this->delete("/admin/dogs/{$dog->id}/vaccinations/{$vaccination->id}");

        $response->assertRedirect(route('admin.dogs.show', $dog));
        $this->assertDatabaseMissing('dog_vaccinations', ['id' => $vaccination->id]);
    }

    public function test_destroy_vaccination_returns_404_for_wrong_dog(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();
        $otherDog = Dog::factory()->forCustomer($customer)->create();
        $vaccination = DogVaccination::factory()->create(['tenant_id' => $this->tenant->id, 'dog_id' => $otherDog->id]);

        $this->actingAs($this->staff);

        $response = $this->delete("/admin/dogs/{$dog->id}/vaccinations/{$vaccination->id}");

        $response->assertStatus(404);
    }

    public function test_update_saves_status(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['status' => 'active']);

        $this->actingAs($this->staff);

        $response = $this->patch("/admin/dogs/{$dog->id}", [
            'name' => $dog->name,
            'status' => 'suspended',
        ]);

        $response->assertRedirect(route('admin.dogs.show', $dog));
        $this->assertDatabaseHas('dogs', ['id' => $dog->id, 'status' => 'suspended']);
    }

    public function test_show_includes_status(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['status' => 'inactive']);

        $this->actingAs($this->staff);

        $response = $this->get("/admin/dogs/{$dog->id}");

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Dogs/Show')
            ->where('dog.status', 'inactive')
        );
    }
}
