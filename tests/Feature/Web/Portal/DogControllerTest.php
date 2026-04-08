<?php

namespace Tests\Feature\Web\Portal;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\DogVaccination;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DogControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'portalco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://portalco.pawpass.com');

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'role'        => 'customer',
        ]);
        $this->customer->update(['user_id' => $this->user->id]);
    }

    public function test_show_includes_vaccinations(): void
    {
        $dog = Dog::factory()->forCustomer($this->customer)->create();
        DogVaccination::factory()->create(['tenant_id' => $this->tenant->id, 'dog_id' => $dog->id, 'vaccine_name' => 'Rabies']);

        $this->actingAs($this->user);

        $response = $this->get("/my/dogs/{$dog->id}");

        $response->assertInertia(fn ($page) => $page
            ->component('Portal/Dogs/Show')
            ->has('vaccinations', 1)
            ->where('vaccinations.0.vaccine_name', 'Rabies')
        );
    }

    public function test_show_returns_403_for_other_customer_dog(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherDog = Dog::factory()->forCustomer($otherCustomer)->create();

        $this->actingAs($this->user);

        $response = $this->get("/my/dogs/{$otherDog->id}");

        $response->assertStatus(403);
    }

    public function test_customer_can_add_vaccination_to_own_dog(): void
    {
        $dog = Dog::factory()->forCustomer($this->customer)->create();

        $this->actingAs($this->user);

        $response = $this->post("/my/dogs/{$dog->id}/vaccinations", [
            'vaccine_name'    => 'Rabies',
            'administered_at' => '2025-01-15',
            'expires_at'      => '2026-01-15',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dog_vaccinations', [
            'dog_id'       => $dog->id,
            'vaccine_name' => 'Rabies',
        ]);
    }

    public function test_customer_cannot_add_vaccination_to_another_customers_dog(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherDog = Dog::factory()->forCustomer($otherCustomer)->create();

        $this->actingAs($this->user);

        $response = $this->post("/my/dogs/{$otherDog->id}/vaccinations", [
            'vaccine_name'    => 'Rabies',
            'administered_at' => '2025-01-15',
        ]);

        $response->assertStatus(403);
    }

    public function test_customer_can_delete_own_dogs_vaccination(): void
    {
        $dog = Dog::factory()->forCustomer($this->customer)->create();
        $vaccination = DogVaccination::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id'    => $dog->id,
        ]);

        $this->actingAs($this->user);

        $response = $this->delete("/my/dogs/{$dog->id}/vaccinations/{$vaccination->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('dog_vaccinations', ['id' => $vaccination->id]);
    }

    public function test_customer_cannot_delete_another_customers_dogs_vaccination(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherDog = Dog::factory()->forCustomer($otherCustomer)->create();
        $vaccination = DogVaccination::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id'    => $otherDog->id,
        ]);

        $this->actingAs($this->user);

        $response = $this->delete("/my/dogs/{$otherDog->id}/vaccinations/{$vaccination->id}");

        $response->assertStatus(403);
    }

    public function test_vaccination_store_validates_required_fields(): void
    {
        $dog = Dog::factory()->forCustomer($this->customer)->create();

        $this->actingAs($this->user);

        $response = $this->post("/my/dogs/{$dog->id}/vaccinations", []);

        $response->assertSessionHasErrors(['vaccine_name', 'administered_at']);
    }
}
