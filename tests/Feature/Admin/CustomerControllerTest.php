<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class CustomerControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        PlatformPlan::factory()->create([
            'slug'     => 'starter',
            'features' => ['add_customers', 'add_dogs', 'customer_portal', 'email_notifications', 'basic_reporting'],
        ]);

        $this->tenant = Tenant::factory()->create(['slug' => 'admincust', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://admincust.pawpass.com');

        $this->staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];
    }

    public function test_staff_can_list_customers(): void
    {
        Customer::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $otherTenant = Tenant::factory()->create(['slug' => 'other-cust-list', 'status' => 'active']);
        Customer::factory()->count(2)->create(['tenant_id' => $otherTenant->id]); // other tenants

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/customers');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_search_filters_by_name(): void
    {
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Alice Smith']);
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bob Jones']);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/customers?search=Alice');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Alice Smith', $response->json('data.0.name'));
    }

    public function test_search_filters_by_email(): void
    {
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'alice@test.com']);
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'bob@test.com']);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/customers?search=alice');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_staff_can_create_customer_without_email(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/customers', [
                'name' => 'No Email Customer',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'No Email Customer');

        $this->assertDatabaseHas('customers', ['name' => 'No Email Customer']);
        $this->assertDatabaseCount('users', 1); // only the staff user
    }

    public function test_staff_can_create_customer_with_email_also_creates_user(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/customers', [
                'name' => 'Email Customer',
                'email' => 'customer@example.com',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('customers', ['email' => 'customer@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'customer@example.com', 'role' => 'customer']);
    }

    public function test_staff_can_view_customer_with_dogs(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Dog::factory()->forCustomer($customer)->count(2)->create();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/admin/v1/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $customer->id);

        $this->assertCount(2, $response->json('data.dogs'));
    }

    public function test_staff_can_update_customer(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->patchJson("/api/admin/v1/customers/{$customer->id}", [
                'name' => 'Updated Name',
                'notes' => 'Some notes',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_cross_tenant_customer_returns_404(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other-cust-tenant', 'status' => 'active']);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/admin/v1/customers/{$otherCustomer->id}");

        $response->assertStatus(404);
    }

    public function test_customer_role_cannot_access_admin_endpoints(): void
    {
        $customerUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'customer',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->jwtFor($customerUser)])
            ->getJson('/api/admin/v1/customers');

        $response->assertStatus(403);
    }
}
