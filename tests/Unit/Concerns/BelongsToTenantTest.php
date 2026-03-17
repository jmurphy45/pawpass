<?php

namespace Tests\Unit\Concerns;

use App\Models\Dog;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BelongsToTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear any existing tenant binding
        app()->forgetInstance('current.tenant.id');
    }

    public function test_queries_are_scoped_to_current_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $customerA = \App\Models\Customer::factory()->create(['tenant_id' => $tenantA->id]);
        $customerB = \App\Models\Customer::factory()->create(['tenant_id' => $tenantB->id]);

        $dogA = Dog::factory()->create(['tenant_id' => $tenantA->id, 'customer_id' => $customerA->id]);
        $dogB = Dog::factory()->create(['tenant_id' => $tenantB->id, 'customer_id' => $customerB->id]);

        app()->instance('current.tenant.id', $tenantA->id);

        $dogs = Dog::all();

        $this->assertCount(1, $dogs);
        $this->assertTrue($dogs->first()->is($dogA));
        $this->assertFalse($dogs->contains($dogB));
    }

    public function test_cross_tenant_record_is_invisible(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $customerB = \App\Models\Customer::factory()->create(['tenant_id' => $tenantB->id]);
        $dogB = Dog::factory()->create(['tenant_id' => $tenantB->id, 'customer_id' => $customerB->id]);

        app()->instance('current.tenant.id', $tenantA->id);

        $found = Dog::find($dogB->id);

        $this->assertNull($found);
    }

    public function test_withoutscopeescapehatch_returns_all_records(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $customerA = \App\Models\Customer::factory()->create(['tenant_id' => $tenantA->id]);
        $customerB = \App\Models\Customer::factory()->create(['tenant_id' => $tenantB->id]);

        Dog::factory()->create(['tenant_id' => $tenantA->id, 'customer_id' => $customerA->id]);
        Dog::factory()->create(['tenant_id' => $tenantB->id, 'customer_id' => $customerB->id]);

        app()->instance('current.tenant.id', $tenantA->id);

        $allDogs = Dog::allTenants()->get();

        $this->assertCount(2, $allDogs);
    }

    public function test_model_auto_fills_tenant_id_on_create(): void
    {
        $tenant = Tenant::factory()->create();
        $customer = \App\Models\Customer::factory()->create(['tenant_id' => $tenant->id]);

        app()->instance('current.tenant.id', $tenant->id);

        $dog = Dog::create([
            'customer_id' => $customer->id,
            'name' => 'Buddy',
            'credit_balance' => 0,
        ]);

        $this->assertSame($tenant->id, $dog->tenant_id);
    }

    public function test_no_scope_applied_when_no_tenant_in_context(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $customerA = \App\Models\Customer::factory()->create(['tenant_id' => $tenantA->id]);
        $customerB = \App\Models\Customer::factory()->create(['tenant_id' => $tenantB->id]);

        Dog::factory()->create(['tenant_id' => $tenantA->id, 'customer_id' => $customerA->id]);
        Dog::factory()->create(['tenant_id' => $tenantB->id, 'customer_id' => $customerB->id]);

        // No tenant bound — scope applies null, returns nothing (WHERE tenant_id = null)
        // But our TenantScope skips the WHERE if tenant is null
        $dogs = Dog::all();

        $this->assertCount(2, $dogs);
    }
}
