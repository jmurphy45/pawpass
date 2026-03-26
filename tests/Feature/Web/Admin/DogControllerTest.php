<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Package;
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

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
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
            'name'        => 'Buddy',
            'breed'       => 'Labrador',
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
            'name'  => 'New Name',
            'breed' => 'Poodle',
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
            'name'                      => $dog->name,
            'auto_replenish_enabled'    => true,
            'auto_replenish_package_id' => $package->id,
        ]);

        $response->assertRedirect(route('admin.dogs.show', $dog));
        $this->assertDatabaseHas('dogs', [
            'id'                        => $dog->id,
            'auto_replenish_enabled'    => true,
            'auto_replenish_package_id' => $package->id,
        ]);
    }

    public function test_update_clears_package_when_auto_replenish_disabled(): void
    {
        $package = Package::factory()->autoReplenish()->create(['tenant_id' => $this->tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create([
            'auto_replenish_enabled'    => true,
            'auto_replenish_package_id' => $package->id,
        ]);

        $this->actingAs($this->staff);

        $this->patch("/admin/dogs/{$dog->id}", [
            'name'                   => $dog->name,
            'auto_replenish_enabled' => false,
        ]);

        $this->assertDatabaseHas('dogs', [
            'id'                        => $dog->id,
            'auto_replenish_enabled'    => false,
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
}
