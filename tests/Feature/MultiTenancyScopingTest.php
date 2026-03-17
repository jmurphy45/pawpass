<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenancyScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance('current.tenant.id');
    }

    public function test_dog_created_for_tenant_a_is_invisible_to_tenant_b(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id]);
        $dogA = Dog::factory()->create(['tenant_id' => $tenantA->id, 'customer_id' => $customerA->id]);

        app()->instance('current.tenant.id', $tenantB->id);

        $this->assertCount(0, Dog::all());
        $this->assertNull(Dog::find($dogA->id));
    }

    public function test_each_tenant_sees_only_their_own_dogs(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id]);
        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);

        Dog::factory()->count(3)->create(['tenant_id' => $tenantA->id, 'customer_id' => $customerA->id]);
        Dog::factory()->count(2)->create(['tenant_id' => $tenantB->id, 'customer_id' => $customerB->id]);

        app()->instance('current.tenant.id', $tenantA->id);
        $this->assertCount(3, Dog::all());

        app()->instance('current.tenant.id', $tenantB->id);
        $this->assertCount(2, Dog::all());
    }

    public function test_all_tenants_escape_hatch_returns_all_records(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id]);
        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);

        Dog::factory()->count(2)->create(['tenant_id' => $tenantA->id, 'customer_id' => $customerA->id]);
        Dog::factory()->count(3)->create(['tenant_id' => $tenantB->id, 'customer_id' => $customerB->id]);

        app()->instance('current.tenant.id', $tenantA->id);

        $this->assertCount(5, Dog::allTenants()->get());
    }

    public function test_soft_deleted_dogs_hidden_by_default(): void
    {
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $dog = Dog::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);
        $dog->delete();

        app()->instance('current.tenant.id', $tenant->id);

        $this->assertCount(0, Dog::all());
    }

    public function test_soft_deleted_dogs_visible_with_trashed(): void
    {
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $dog = Dog::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);
        $dog->delete();

        app()->instance('current.tenant.id', $tenant->id);

        $this->assertCount(1, Dog::withTrashed()->get());
    }

    public function test_invalid_fk_reference_raises_db_exception(): void
    {
        $tenant = Tenant::factory()->create();

        // A non-existent customer_id violates the FK constraint
        $this->expectException(\Illuminate\Database\QueryException::class);

        Dog::forceCreate([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'tenant_id' => $tenant->id,
            'customer_id' => '00000000000000000000000000', // does not exist
            'name' => 'BadDog',
            'credit_balance' => 0,
        ]);
    }
}
