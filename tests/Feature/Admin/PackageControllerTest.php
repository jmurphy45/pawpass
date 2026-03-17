<?php

namespace Tests\Feature\Admin;

use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class PackageControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create([
            'slug' => 'pkgadmin',
            'status' => 'active',
            'stripe_account_id' => 'acct_pkgadmin',
            'stripe_onboarded_at' => now(),
        ]);
        URL::forceRootUrl('http://pkgadmin.pawpass.com');

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'business_owner',
        ]);

        $this->staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);
    }

    private function ownerHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->owner)];
    }

    private function staffHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];
    }

    // --- index ---

    public function test_index_returns_non_archived_packages_for_tenant(): void
    {
        Package::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Active Pack']);
        Package::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Archived Pack'])->delete();

        $response = $this->withHeaders($this->ownerHeaders())
            ->getJson('/api/admin/v1/packages');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Active Pack', $response->json('data.0.name'));
    }

    public function test_index_does_not_return_other_tenant_packages(): void
    {
        $other = Tenant::factory()->create(['slug' => 'otherpkgadmin', 'status' => 'active']);
        Package::factory()->create(['tenant_id' => $other->id]);
        Package::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'My Pack']);

        $response = $this->withHeaders($this->staffHeaders())
            ->getJson('/api/admin/v1/packages');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('My Pack', $response->json('data.0.name'));
    }

    public function test_index_returns_new_fields(): void
    {
        Package::factory()->multiDog(2)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->staffHeaders())
            ->getJson('/api/admin/v1/packages');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name', 'description', 'type', 'price', 'credit_count', 'dog_limit', 'duration_days', 'is_active']]]);
        $this->assertEquals(2, $response->json('data.0.dog_limit'));
    }

    // --- store ---

    public function test_store_creates_one_time_package(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/packages', [
                'name' => '10-Day Pack',
                'type' => 'one_time',
                'price' => '89.00',
                'credit_count' => 10,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', '10-Day Pack')
            ->assertJsonPath('data.credit_count', 10)
            ->assertJsonPath('data.dog_limit', 1);

        $this->assertDatabaseHas('packages', ['name' => '10-Day Pack', 'tenant_id' => $this->tenant->id]);
    }

    public function test_store_creates_unlimited_package(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/packages', [
                'name' => '30-Day Pass',
                'type' => 'unlimited',
                'price' => '150.00',
                'duration_days' => 30,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'unlimited')
            ->assertJsonPath('data.duration_days', 30)
            ->assertJsonPath('data.credit_count', null);
    }

    public function test_store_requires_credit_count_for_one_time(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/packages', [
                'name' => 'Pack',
                'type' => 'one_time',
                'price' => '50.00',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['credit_count']);
    }

    public function test_store_requires_duration_days_for_unlimited(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/packages', [
                'name' => 'Pass',
                'type' => 'unlimited',
                'price' => '100.00',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['duration_days']);
    }

    public function test_store_prohibits_credit_count_for_unlimited(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/packages', [
                'name' => 'Pass',
                'type' => 'unlimited',
                'price' => '100.00',
                'duration_days' => 30,
                'credit_count' => 10,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['credit_count']);
    }

    public function test_store_prohibits_duration_days_for_one_time(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/packages', [
                'name' => 'Pack',
                'type' => 'one_time',
                'price' => '50.00',
                'credit_count' => 5,
                'duration_days' => 30,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['duration_days']);
    }

    public function test_store_rejects_bad_type(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/packages', [
                'name' => 'Pack',
                'type' => 'invalid_type',
                'price' => '50.00',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_store_is_forbidden_for_staff(): void
    {
        $response = $this->withHeaders($this->staffHeaders())
            ->postJson('/api/admin/v1/packages', [
                'name' => 'Pack',
                'type' => 'one_time',
                'price' => '50.00',
                'credit_count' => 5,
            ]);

        $response->assertStatus(403);
    }

    // --- update ---

    public function test_update_modifies_package(): void
    {
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Old Name']);

        $response = $this->withHeaders($this->ownerHeaders())
            ->patchJson("/api/admin/v1/packages/{$package->id}", [
                'name' => 'New Name',
                'dog_limit' => 3,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.dog_limit', 3);
    }

    public function test_update_returns_404_for_other_tenant_package(): void
    {
        $other = Tenant::factory()->create(['slug' => 'otherupdate', 'status' => 'active']);
        $package = Package::factory()->create(['tenant_id' => $other->id]);

        $response = $this->withHeaders($this->ownerHeaders())
            ->patchJson("/api/admin/v1/packages/{$package->id}", ['name' => 'Hack']);

        $response->assertStatus(404);
    }

    public function test_update_is_forbidden_for_staff(): void
    {
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->staffHeaders())
            ->patchJson("/api/admin/v1/packages/{$package->id}", ['name' => 'Changed']);

        $response->assertStatus(403);
    }

    // --- archive ---

    public function test_archive_soft_deletes_and_deactivates_package(): void
    {
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson("/api/admin/v1/packages/{$package->id}/archive");

        $response->assertStatus(200);

        $this->assertSoftDeleted('packages', ['id' => $package->id]);
        $this->assertDatabaseHas('packages', ['id' => $package->id, 'is_active' => false]);
    }

    public function test_archive_returns_409_if_already_archived(): void
    {
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);
        $package->delete();

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson("/api/admin/v1/packages/{$package->id}/archive");

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'ALREADY_ARCHIVED');
    }

    public function test_archive_is_forbidden_for_staff(): void
    {
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->staffHeaders())
            ->postJson("/api/admin/v1/packages/{$package->id}/archive");

        $response->assertStatus(403);
    }
}
