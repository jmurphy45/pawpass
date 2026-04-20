<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DashboardOutstandingBalancesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'baltest', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://baltest.pawpass.com');

        $this->staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);
    }

    public function test_dashboard_includes_outstanding_balance_props(): void
    {
        $this->actingAs($this->staff);

        $response = $this->get('/admin');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->has('outstandingTotal')
            ->has('outstandingCount')
            ->has('outstandingCustomers')
        );
    }

    public function test_only_customers_with_balance_appear(): void
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Owing Customer',
            'outstanding_balance_cents' => 5000,
        ]);
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Clean Customer',
            'outstanding_balance_cents' => 0,
        ]);

        $this->actingAs($this->staff);

        $response = $this->get('/admin');

        $response->assertInertia(fn ($page) => $page
            ->where('outstandingCount', 1)
            ->where('outstandingTotal', 5000)
            ->has('outstandingCustomers', 1)
            ->where('outstandingCustomers.0.name', 'Owing Customer')
        );
    }

    public function test_outstanding_total_and_count_are_correct(): void
    {
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'outstanding_balance_cents' => 10000]);
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'outstanding_balance_cents' => 3500]);
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'outstanding_balance_cents' => 0]);

        $this->actingAs($this->staff);

        $response = $this->get('/admin');

        $response->assertInertia(fn ($page) => $page
            ->where('outstandingTotal', 13500)
            ->where('outstandingCount', 2)
        );
    }

    public function test_customers_are_ordered_by_balance_descending(): void
    {
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Small', 'outstanding_balance_cents' => 1000]);
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Large', 'outstanding_balance_cents' => 9000]);
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Medium', 'outstanding_balance_cents' => 4500]);

        $this->actingAs($this->staff);

        $response = $this->get('/admin');

        $response->assertInertia(fn ($page) => $page
            ->where('outstandingCustomers.0.name', 'Large')
            ->where('outstandingCustomers.1.name', 'Medium')
            ->where('outstandingCustomers.2.name', 'Small')
        );
    }

    public function test_has_payment_method_is_true_only_when_both_ids_present(): void
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Has Card',
            'outstanding_balance_cents' => 5000,
            'stripe_customer_id' => 'cus_test',
            'stripe_payment_method_id' => 'pm_test',
        ]);
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'No Card',
            'outstanding_balance_cents' => 3000,
            'stripe_customer_id' => null,
            'stripe_payment_method_id' => null,
        ]);

        $this->actingAs($this->staff);

        $response = $this->get('/admin');

        $response->assertInertia(fn ($page) => $page
            ->where('outstandingCustomers.0.has_payment_method', true)
            ->where('outstandingCustomers.1.has_payment_method', false)
        );
    }

    public function test_cross_tenant_customers_excluded(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other-bal', 'status' => 'active']);
        Customer::factory()->create([
            'tenant_id' => $otherTenant->id,
            'outstanding_balance_cents' => 99999,
        ]);
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'outstanding_balance_cents' => 1000,
        ]);

        $this->actingAs($this->staff);

        $response = $this->get('/admin');

        $response->assertInertia(fn ($page) => $page
            ->where('outstandingCount', 1)
            ->where('outstandingTotal', 1000)
        );
    }
}
