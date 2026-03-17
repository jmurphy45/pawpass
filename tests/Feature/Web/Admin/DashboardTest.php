<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    public function test_staff_can_view_dashboard(): void
    {
        $this->actingAs($this->staff);

        $response = $this->get('/admin');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/Dashboard'));
    }

    public function test_dashboard_contains_correct_props(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Dog::factory()->forCustomer($customer)->create(['credit_balance' => 0]);

        $this->actingAs($this->staff);

        $response = $this->get('/admin');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->has('checkinsToday')
            ->has('customersCount')
            ->has('dogsCount')
            ->has('lowCreditDogs')
            ->has('recentAttendance')
        );
    }

    public function test_business_owner_can_view_dashboard(): void
    {
        $owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => 'business_owner',
            'status'    => 'active',
        ]);

        $this->actingAs($owner);

        $response = $this->get('/admin');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/Dashboard'));
    }

    public function test_customer_cannot_access_admin_dashboard(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $customerUser = User::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $customer->id,
            'role'        => 'customer',
            'status'      => 'active',
        ]);

        $this->actingAs($customerUser);

        $response = $this->get('/admin');

        $response->assertRedirect(route('admin.login'));
    }

    public function test_cross_tenant_data_not_visible(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other', 'status' => 'active', 'plan' => 'starter']);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        Dog::factory()->forCustomer($otherCustomer)->create(['credit_balance' => 0]);

        // Only create our tenant's customer
        $myCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Dog::factory()->forCustomer($myCustomer)->create(['credit_balance' => 5]);

        $this->actingAs($this->staff);

        $response = $this->get('/admin');

        $response->assertInertia(fn ($page) => $page
            ->where('customersCount', 1)
            ->where('dogsCount', 1)
        );
    }
}
