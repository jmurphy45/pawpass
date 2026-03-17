<?php

namespace Tests\Feature\Admin;

use App\Models\CreditLedger;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class DogControllerTest extends TestCase
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

        $this->tenant = Tenant::factory()->create(['slug' => 'admindog', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://admindog.pawpass.com');

        $this->staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];
    }

    public function test_staff_can_list_all_dogs_for_tenant(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Dog::factory()->forCustomer($customer)->count(4)->create();

        // Create dogs in a completely separate tenant
        $otherTenant = Tenant::factory()->create(['slug' => 'other-tenant-list', 'status' => 'active']);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        Dog::factory()->forCustomer($otherCustomer)->count(2)->create();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/dogs');

        $response->assertStatus(200);
        $this->assertCount(4, $response->json('data'));
    }

    public function test_staff_can_create_dog(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/dogs', [
                'customer_id' => $customer->id,
                'name' => 'Rocky',
                'breed' => 'Beagle',
                'sex' => 'male',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Rocky');

        $this->assertDatabaseHas('dogs', ['name' => 'Rocky', 'customer_id' => $customer->id]);
    }

    public function test_create_dog_with_invalid_customer_returns_404(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/dogs', [
                'customer_id' => 'nonexistent',
                'name' => 'Ghost',
            ]);

        $response->assertStatus(404);
    }

    public function test_staff_can_view_dog_with_recent_ledger(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();

        CreditLedger::factory()->count(12)->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/admin/v1/dogs/{$dog->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $dog->id);

        $this->assertCount(10, $response->json('meta.recent_ledger'));
    }

    public function test_staff_can_update_dog(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['name' => 'Before']);

        $response = $this->withHeaders($this->authHeaders())
            ->patchJson("/api/admin/v1/dogs/{$dog->id}", ['name' => 'After']);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'After');
    }

    public function test_staff_can_soft_delete_dog(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/admin/v1/dogs/{$dog->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('dogs', ['id' => $dog->id]);
    }

    public function test_cross_tenant_dog_returns_404(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other-tenant-dog', 'status' => 'active']);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherDog = Dog::factory()->forCustomer($otherCustomer)->create();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/admin/v1/dogs/{$otherDog->id}");

        $response->assertStatus(404);
    }

    public function test_deleted_dog_not_listed(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();
        $dog->delete();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/dogs');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }
}
