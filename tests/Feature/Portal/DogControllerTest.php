<?php

namespace Tests\Feature\Portal;

use App\Models\CreditLedger;
use App\Models\Customer;
use App\Models\Dog;
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

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'dogportal', 'status' => 'active']);
        URL::forceRootUrl('http://dogportal.pawpass.com');

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'role' => 'customer',
        ]);
        $this->customer->update(['user_id' => $this->user->id]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->user)];
    }

    public function test_customer_can_list_their_dogs(): void
    {
        Dog::factory()->forCustomer($this->customer)->count(3)->create();
        Dog::factory()->count(2)->create(); // other tenant dogs

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/dogs');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_customer_can_create_a_dog(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/portal/v1/dogs', [
                'name' => 'Buddy',
                'breed' => 'Labrador',
                'sex' => 'male',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Buddy')
            ->assertJsonPath('data.breed', 'Labrador');

        $this->assertDatabaseHas('dogs', [
            'name' => 'Buddy',
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_create_dog_requires_name(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/portal/v1/dogs', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_customer_can_view_their_dog(): void
    {
        $dog = Dog::factory()->forCustomer($this->customer)->create();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/portal/v1/dogs/{$dog->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $dog->id);
    }

    public function test_customer_cannot_view_another_customers_dog(): void
    {
        $otherDog = Dog::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/portal/v1/dogs/{$otherDog->id}");

        $response->assertStatus(404);
    }

    public function test_soft_deleted_dog_returns_404(): void
    {
        $dog = Dog::factory()->forCustomer($this->customer)->create();
        $dog->delete();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/portal/v1/dogs/{$dog->id}");

        $response->assertStatus(404);
    }

    public function test_customer_can_update_their_dog(): void
    {
        $dog = Dog::factory()->forCustomer($this->customer)->create(['name' => 'OldName']);

        $response = $this->withHeaders($this->authHeaders())
            ->patchJson("/api/portal/v1/dogs/{$dog->id}", ['name' => 'NewName']);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'NewName');
    }

    public function test_customer_cannot_update_another_customers_dog(): void
    {
        $otherDog = Dog::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->patchJson("/api/portal/v1/dogs/{$otherDog->id}", ['name' => 'Hacked']);

        $response->assertStatus(403);
    }

    public function test_customer_can_view_dog_credit_ledger(): void
    {
        $dog = Dog::factory()->forCustomer($this->customer)->create();

        CreditLedger::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/portal/v1/dogs/{$dog->id}/credits");

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
    }

    public function test_credit_ledger_paginated_cursor(): void
    {
        $dog = Dog::factory()->forCustomer($this->customer)->create();

        CreditLedger::factory()->count(25)->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/portal/v1/dogs/{$dog->id}/credits");

        $response->assertStatus(200);
        $this->assertCount(20, $response->json('data'));
        $this->assertArrayHasKey('next_cursor', $response->json('meta'));
    }

    public function test_cannot_view_credit_ledger_of_another_customers_dog(): void
    {
        $otherDog = Dog::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/portal/v1/dogs/{$otherDog->id}/credits");

        $response->assertStatus(404);
    }
}
