<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\DogVaccination;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class DogVaccinationControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Dog $dog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'vax-test', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://vax-test.pawpass.com');

        $this->staff = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->dog = Dog::factory()->forCustomer($customer)->create();
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];
    }

    public function test_index_returns_vaccinations_for_dog(): void
    {
        DogVaccination::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/admin/v1/dogs/{$this->dog->id}/vaccinations");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_cross_tenant_isolation(): void
    {
        $other = Tenant::factory()->create(['slug' => 'other-vax', 'status' => 'active']);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $other->id]);
        $otherDog = Dog::factory()->create(['tenant_id' => $other->id, 'customer_id' => $otherCustomer->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/admin/v1/dogs/{$otherDog->id}/vaccinations");

        $response->assertStatus(404);
    }

    public function test_store_creates_vaccination(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson(
            "/api/admin/v1/dogs/{$this->dog->id}/vaccinations",
            [
                'vaccine_name'    => 'Rabies',
                'administered_at' => '2025-06-15',
                'expires_at'      => '2026-06-15',
                'administered_by' => 'Happy Paws Vet',
            ]
        );

        $response->assertStatus(201);
        $response->assertJsonPath('data.vaccine_name', 'Rabies');
        $response->assertJsonPath('data.is_valid', true);
        $this->assertDatabaseHas('dog_vaccinations', ['dog_id' => $this->dog->id, 'vaccine_name' => 'Rabies']);
    }

    public function test_store_requires_vaccine_name_and_date(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson(
            "/api/admin/v1/dogs/{$this->dog->id}/vaccinations",
            []
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['vaccine_name', 'administered_at']);
    }

    public function test_store_vaccine_without_expiry(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson(
            "/api/admin/v1/dogs/{$this->dog->id}/vaccinations",
            ['vaccine_name' => 'DHPP', 'administered_at' => '2025-01-01']
        );

        $response->assertStatus(201);
        $response->assertJsonPath('data.expires_at', null);
        $response->assertJsonPath('data.is_valid', true);
    }

    public function test_update_vaccination(): void
    {
        $vax = DogVaccination::factory()->create(['tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id]);

        $response = $this->withHeaders($this->authHeaders())->patchJson(
            "/api/admin/v1/dogs/{$this->dog->id}/vaccinations/{$vax->id}",
            ['vaccine_name' => 'Bordetella Updated']
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.vaccine_name', 'Bordetella Updated');
    }

    public function test_update_rejects_wrong_dog(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherDog = Dog::factory()->forCustomer($otherCustomer)->create();
        $vax = DogVaccination::factory()->create(['tenant_id' => $this->tenant->id, 'dog_id' => $otherDog->id]);

        $response = $this->withHeaders($this->authHeaders())->patchJson(
            "/api/admin/v1/dogs/{$this->dog->id}/vaccinations/{$vax->id}",
            ['vaccine_name' => 'Rabies']
        );

        $response->assertStatus(404);
    }

    public function test_destroy_vaccination(): void
    {
        $vax = DogVaccination::factory()->create(['tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/admin/v1/dogs/{$this->dog->id}/vaccinations/{$vax->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('dog_vaccinations', ['id' => $vax->id]);
    }

    public function test_expired_vaccination_reports_is_valid_false(): void
    {
        $vax = DogVaccination::factory()->expired()->create(['tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/admin/v1/dogs/{$this->dog->id}/vaccinations");

        $response->assertStatus(200);
        $this->assertFalse($response->json('data.0.is_valid'));
    }
}
