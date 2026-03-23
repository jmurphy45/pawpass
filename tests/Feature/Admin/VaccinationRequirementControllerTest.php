<?php

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VaccinationRequirement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class VaccinationRequirementControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'vaxreq-test', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://vaxreq-test.pawpass.com');

        $this->staff = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff']);
        $this->owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'business_owner']);
    }

    private function staffHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];
    }

    private function ownerHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->owner)];
    }

    public function test_index_returns_tenant_requirements(): void
    {
        VaccinationRequirement::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $other = Tenant::factory()->create(['slug' => 'other-vaxreq', 'status' => 'active']);
        VaccinationRequirement::factory()->create(['tenant_id' => $other->id]);

        $response = $this->withHeaders($this->staffHeaders())->getJson('/api/admin/v1/vaccination-requirements');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_owner_can_add_requirement(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())->postJson('/api/admin/v1/vaccination-requirements', [
            'vaccine_name' => 'Rabies',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.vaccine_name', 'Rabies');
    }

    public function test_staff_cannot_add_requirement(): void
    {
        $response = $this->withHeaders($this->staffHeaders())->postJson('/api/admin/v1/vaccination-requirements', [
            'vaccine_name' => 'Rabies',
        ]);

        $response->assertStatus(403);
    }

    public function test_duplicate_requirement_returns_409(): void
    {
        VaccinationRequirement::factory()->create(['tenant_id' => $this->tenant->id, 'vaccine_name' => 'Rabies']);

        $response = $this->withHeaders($this->ownerHeaders())->postJson('/api/admin/v1/vaccination-requirements', [
            'vaccine_name' => 'Rabies',
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'VACCINE_REQUIREMENT_ALREADY_EXISTS');
    }

    public function test_owner_can_delete_requirement(): void
    {
        $req = VaccinationRequirement::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->ownerHeaders())
            ->deleteJson("/api/admin/v1/vaccination-requirements/{$req->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('vaccination_requirements', ['id' => $req->id]);
    }

    public function test_cross_tenant_requirement_not_accessible(): void
    {
        $other = Tenant::factory()->create(['slug' => 'other-vaxreq2', 'status' => 'active']);
        $req = VaccinationRequirement::factory()->create(['tenant_id' => $other->id]);

        $response = $this->withHeaders($this->ownerHeaders())
            ->deleteJson("/api/admin/v1/vaccination-requirements/{$req->id}");

        $response->assertStatus(404);
    }
}
