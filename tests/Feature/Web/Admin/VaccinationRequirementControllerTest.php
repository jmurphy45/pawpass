<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VaccinationRequirement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VaccinationRequirementControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->owner = User::factory()->businessOwner()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);

        $this->staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
    }

    public function test_index_lists_requirements(): void
    {
        VaccinationRequirement::factory()->create(['tenant_id' => $this->tenant->id, 'vaccine_name' => 'Rabies']);
        VaccinationRequirement::factory()->create(['tenant_id' => $this->tenant->id, 'vaccine_name' => 'Bordetella']);

        $this->actingAs($this->owner);

        $response = $this->get('/admin/vaccination-requirements');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/VaccinationRequirements/Index')
            ->has('requirements', 2)
        );
    }

    public function test_index_scoped_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other', 'status' => 'active', 'plan' => 'starter']);
        VaccinationRequirement::factory()->create(['tenant_id' => $this->tenant->id, 'vaccine_name' => 'Rabies']);
        VaccinationRequirement::factory()->create(['tenant_id' => $otherTenant->id, 'vaccine_name' => 'Bordetella']);

        $this->actingAs($this->owner);

        $response = $this->get('/admin/vaccination-requirements');

        $response->assertInertia(fn ($page) => $page->has('requirements', 1));
    }

    public function test_store_creates_requirement_for_owner(): void
    {
        $this->actingAs($this->owner);

        $response = $this->post('/admin/vaccination-requirements', ['vaccine_name' => 'Rabies']);

        $response->assertRedirect(route('admin.vaccination-requirements.index'));
        $this->assertDatabaseHas('vaccination_requirements', [
            'tenant_id'    => $this->tenant->id,
            'vaccine_name' => 'Rabies',
        ]);
    }

    public function test_store_forbidden_for_staff(): void
    {
        $this->actingAs($this->staff);

        $response = $this->post('/admin/vaccination-requirements', ['vaccine_name' => 'Rabies']);

        $response->assertStatus(403);
    }

    public function test_store_validates_vaccine_name(): void
    {
        $this->actingAs($this->owner);

        $response = $this->post('/admin/vaccination-requirements', []);

        $response->assertSessionHasErrors(['vaccine_name']);
    }

    public function test_destroy_removes_requirement(): void
    {
        $req = VaccinationRequirement::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->owner);

        $response = $this->delete("/admin/vaccination-requirements/{$req->id}");

        $response->assertRedirect(route('admin.vaccination-requirements.index'));
        $this->assertDatabaseMissing('vaccination_requirements', ['id' => $req->id]);
    }

    public function test_destroy_forbidden_for_staff(): void
    {
        $req = VaccinationRequirement::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->staff);

        $response = $this->delete("/admin/vaccination-requirements/{$req->id}");

        $response->assertStatus(403);
    }

    public function test_index_accessible_to_staff(): void
    {
        $this->actingAs($this->staff);

        $response = $this->get('/admin/vaccination-requirements');

        $response->assertOk();
    }
}
