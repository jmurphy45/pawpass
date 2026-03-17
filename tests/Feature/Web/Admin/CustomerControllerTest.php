<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
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

    public function test_index_returns_paginated_customers_scoped_to_tenant(): void
    {
        $c1 = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Alice']);
        $c2 = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bob']);

        // Cross-tenant customer should not appear
        $otherTenant = Tenant::factory()->create(['slug' => 'other', 'status' => 'active', 'plan' => 'starter']);
        Customer::factory()->create(['tenant_id' => $otherTenant->id, 'name' => 'Eve']);

        $this->actingAs($this->staff);

        $response = $this->get('/admin/customers');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Customers/Index')
            ->where('customers.total', 2)
        );
    }

    public function test_show_returns_customer_with_dogs(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Dog::factory()->forCustomer($customer)->create(['name' => 'Buddy']);

        $this->actingAs($this->staff);

        $response = $this->get("/admin/customers/{$customer->id}");

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Customers/Show')
            ->where('customer.id', $customer->id)
            ->has('dogs', 1)
        );
    }

    public function test_create_page_renders(): void
    {
        $this->actingAs($this->staff);

        $response = $this->get('/admin/customers/create');

        $response->assertInertia(fn ($page) => $page->component('Admin/Customers/Create'));
    }

    public function test_store_creates_customer_and_redirects(): void
    {
        $this->actingAs($this->staff);

        $response = $this->post('/admin/customers', [
            'name'  => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '555-1234',
        ]);

        $response->assertRedirect(route('admin.customers.index'));
        $this->assertDatabaseHas('customers', ['name' => 'Jane Doe', 'tenant_id' => $this->tenant->id]);
    }

    public function test_store_without_email_creates_customer_without_user(): void
    {
        $this->actingAs($this->staff);

        $response = $this->post('/admin/customers', [
            'name' => 'No Email Customer',
        ]);

        $response->assertRedirect(route('admin.customers.index'));
        $this->assertDatabaseHas('customers', ['name' => 'No Email Customer']);
        $this->assertDatabaseMissing('users', ['name' => 'No Email Customer']);
    }

    public function test_cross_tenant_customer_not_visible(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other', 'status' => 'active', 'plan' => 'starter']);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->actingAs($this->staff);

        $response = $this->get("/admin/customers/{$otherCustomer->id}");

        $response->assertStatus(404);
    }

    public function test_store_syncs_customer_to_stripe_when_account_id_present(): void
    {
        $this->tenant->update(['stripe_account_id' => 'acct_test123']);

        $this->mock(StripeService::class)
            ->shouldReceive('createCustomer')
            ->once()
            ->with('jane@example.com', 'Jane Stripe', 'acct_test123')
            ->andReturn((object) ['id' => 'cus_stripe_new']);

        $this->actingAs($this->staff);

        $this->post('/admin/customers', [
            'name'  => 'Jane Stripe',
            'email' => 'jane@example.com',
        ]);

        $this->assertDatabaseHas('customers', [
            'name'               => 'Jane Stripe',
            'stripe_customer_id' => 'cus_stripe_new',
        ]);
    }

    public function test_store_skips_stripe_when_no_stripe_account(): void
    {
        $this->mock(StripeService::class)
            ->shouldReceive('createCustomer')->never();

        $this->actingAs($this->staff);

        $this->post('/admin/customers', ['name' => 'No Stripe Customer']);

        $this->assertDatabaseHas('customers', ['name' => 'No Stripe Customer']);
    }

    public function test_customer_role_cannot_access_customers_index(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $customerUser = User::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $customer->id,
            'role'        => 'customer',
            'status'      => 'active',
        ]);

        $this->actingAs($customerUser);

        $response = $this->get('/admin/customers');

        $response->assertRedirect(route('admin.login'));
    }
}
