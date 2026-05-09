<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Customer;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerSearchTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        PlatformPlan::factory()->create([
            'slug' => 'starter',
            'features' => ['add_customers', 'add_dogs'],
        ]);

        $this->tenant = Tenant::factory()->create(['slug' => 'searchco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://searchco.pawpass.com');

        $this->staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);
    }

    public function test_returns_matching_customers_as_json(): void
    {
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Fluffy Owner', 'email' => 'fluffy@example.com']);
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bark Person', 'email' => 'bark@example.com']);

        $response = $this->actingAs($this->staff)
            ->getJson(route('admin.customers.search', ['search' => 'Fluffy']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Fluffy Owner')
            ->assertJsonPath('data.0.email', 'fluffy@example.com');
    }

    public function test_does_not_return_other_tenant_customers(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other-search', 'status' => 'active', 'plan' => 'starter']);
        Customer::factory()->create(['tenant_id' => $otherTenant->id, 'name' => 'Other Person', 'email' => 'other@example.com']);
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'My Person', 'email' => 'mine@example.com']);

        $response = $this->actingAs($this->staff)
            ->getJson(route('admin.customers.search', ['search' => 'Person']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'My Person');
    }

    public function test_guest_cannot_search(): void
    {
        $response = $this->get(route('admin.customers.search', ['search' => 'test']));

        $response->assertRedirect(route('admin.login'));
    }
}
