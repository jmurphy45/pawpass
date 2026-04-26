<?php

namespace Tests\Feature\Portal;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class AccountTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'accounttest', 'status' => 'active']);
        URL::forceRootUrl('http://accounttest.pawpass.com');

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Jane Doe',
        ]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'role' => 'customer',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '555-1234',
        ]);
        $this->customer->update(['user_id' => $this->user->id]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->user)];
    }

    public function test_show_returns_account_data(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/account');

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Jane Doe')
            ->assertJsonPath('data.email', 'jane@example.com')
            ->assertJsonPath('data.phone', '555-1234')
            ->assertJsonPath('data.customer_name', 'Jane Doe');
    }

    public function test_update_name_updates_user_and_customer(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->patchJson('/api/portal/v1/account', ['name' => 'Jane Smith']);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Jane Smith');

        $this->assertDatabaseHas('users', ['id' => $this->user->id, 'name' => 'Jane Smith']);
        $this->assertDatabaseHas('customers', ['id' => $this->customer->id, 'name' => 'Jane Smith']);
    }

    public function test_update_email(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->patchJson('/api/portal/v1/account', ['email' => 'new@example.com']);

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'new@example.com');
    }

    public function test_update_email_rejects_duplicate_on_same_tenant(): void
    {
        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'taken@example.com',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->patchJson('/api/portal/v1/account', ['email' => 'taken@example.com']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_email_allows_same_email_on_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'othertenant2', 'status' => 'active']);
        User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'email' => 'shared@example.com',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->patchJson('/api/portal/v1/account', ['email' => 'shared@example.com']);

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'shared@example.com');
    }

    public function test_update_with_no_fields_returns_current_data(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->patchJson('/api/portal/v1/account', []);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Jane Doe');
    }

    public function test_unauthenticated_cannot_access_account(): void
    {
        $this->getJson('/api/portal/v1/account')->assertStatus(401);
    }
}
