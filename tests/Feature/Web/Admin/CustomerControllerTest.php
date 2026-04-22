<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        PlatformPlan::factory()->create(['slug' => 'starter', 'features' => ['add_customers']]);
        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
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
            'name' => 'Jane Doe',
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
            'name' => 'Jane Stripe',
            'email' => 'jane@example.com',
        ]);

        $this->assertDatabaseHas('customers', [
            'name' => 'Jane Stripe',
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

    public function test_store_rejects_duplicate_email_with_validation_error(): void
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'taken@example.com',
        ]);

        $this->actingAs($this->staff);

        $response = $this->post('/admin/customers', [
            'name' => 'Another Customer',
            'email' => 'taken@example.com',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_charge_balance_web_uses_zero_fee_when_founders_plan_under_gmv_cap(): void
    {
        PlatformPlan::factory()->create([
            'slug' => 'founders',
            'features' => [],
            'platform_fee_pct' => 2.0,
            'monthly_gmv_cap_cents' => 10_000_00,
        ]);

        $tenant = Tenant::factory()->create([
            'slug' => 'founders-web',
            'status' => 'active',
            'plan' => 'founders',
            'stripe_account_id' => 'acct_founders_web',
            'platform_fee_pct' => 2.0,
        ]);
        URL::forceRootUrl('http://founders-web.pawpass.com');

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'business_owner',
            'status' => 'active',
        ]);

        $customer = Customer::factory()->for($tenant)->create([
            'outstanding_balance_cents' => 5000,
            'stripe_customer_id' => 'cus_fw_test',
            'stripe_payment_method_id' => 'pm_fw_test',
        ]);

        $capturedFee = null;
        $this->mock(StripeService::class, function (MockInterface $mock) use (&$capturedFee) {
            $mock->shouldReceive('createOutstandingBalancePaymentIntent')
                ->once()
                ->withArgs(function ($amountCents, $stripeAccountId, $applicationFeeCents) use (&$capturedFee) {
                    $capturedFee = $applicationFeeCents;

                    return true;
                })
                ->andReturn((object) ['id' => 'pi_fw_test', 'status' => 'succeeded']);
        });

        $this->actingAs($owner);
        $this->post("/admin/customers/{$customer->id}/charge-balance");

        $this->assertSame(0, $capturedFee, 'Application fee should be 0 when founders tenant is under GMV cap');
    }

    public function test_customer_role_cannot_access_customers_index(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $customerUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'role' => 'customer',
            'status' => 'active',
        ]);

        $this->actingAs($customerUser);

        $response = $this->get('/admin/customers');

        $response->assertRedirect(route('admin.login'));
    }
}
