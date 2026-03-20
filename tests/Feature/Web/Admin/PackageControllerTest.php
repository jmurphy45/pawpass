<?php

namespace Tests\Feature\Web\Admin;

use App\Jobs\SyncPackageToStripe;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PackageControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'slug' => 'testco',
            'status' => 'active',
            'plan' => 'starter',
            'stripe_account_id' => 'acct_testco',
            'stripe_onboarded_at' => now(),
        ]);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => 'business_owner',
            'status'    => 'active',
        ]);
    }

    public function test_owner_can_view_packages(): void
    {
        Package::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Basic Pack']);

        $this->actingAs($this->owner);

        $response = $this->get('/admin/packages');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Packages/Index')
            ->has('packages', 1)
        );
    }

    public function test_staff_cannot_view_packages(): void
    {
        $staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);

        $this->actingAs($staff);

        $response = $this->get('/admin/packages');

        $response->assertStatus(403);
    }

    public function test_owner_can_create_package(): void
    {
        $this->actingAs($this->owner);

        $response = $this->post('/admin/packages', [
            'name'         => 'Day Pack',
            'type'         => 'one_time',
            'price'        => 5000,
            'credit_count' => 10,
            'dog_limit'    => 1,
            'is_active'    => true,
        ]);

        $response->assertRedirect(route('admin.packages.index'));
        $this->assertDatabaseHas('packages', ['name' => 'Day Pack', 'tenant_id' => $this->tenant->id]);
    }

    public function test_owner_can_update_package(): void
    {
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Old']);

        $this->actingAs($this->owner);

        $response = $this->patch("/admin/packages/{$package->id}", [
            'name'         => 'New Name',
            'price'        => 6000,
            'credit_count' => 12,
            'dog_limit'    => 1,
        ]);

        $response->assertRedirect(route('admin.packages.index'));
        $this->assertDatabaseHas('packages', ['id' => $package->id, 'name' => 'New Name']);
    }

    public function test_archive_marks_package_deleted_and_inactive(): void
    {
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->owner);

        $response = $this->post("/admin/packages/{$package->id}/archive");

        $response->assertRedirect(route('admin.packages.index'));
        $this->assertSoftDeleted('packages', ['id' => $package->id]);
    }

    public function test_owner_can_update_auto_replenish_eligible(): void
    {
        Queue::fake();

        $package = Package::factory()->create([
            'tenant_id'                 => $this->tenant->id,
            'type'                      => 'one_time',
            'name'                      => 'Old',
            'is_auto_replenish_eligible' => false,
            'stripe_product_id'         => 'prod_existing',
        ]);

        $this->actingAs($this->owner);

        $response = $this->patch("/admin/packages/{$package->id}", [
            'name'                      => 'Old',
            'price'                     => $package->price,
            'credit_count'              => $package->credit_count,
            'dog_limit'                 => 1,
            'is_auto_replenish_eligible' => true,
        ]);

        $response->assertRedirect(route('admin.packages.index'));

        $this->assertDatabaseHas('packages', [
            'id'                        => $package->id,
            'is_auto_replenish_eligible' => true,
        ]);
    }

    public function test_edit_page_includes_auto_replenish_eligible(): void
    {
        $package = Package::factory()->create([
            'tenant_id'                 => $this->tenant->id,
            'is_auto_replenish_eligible' => true,
        ]);

        $this->actingAs($this->owner);

        $response = $this->get("/admin/packages/{$package->id}/edit");

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Packages/Edit')
            ->where('package.is_auto_replenish_eligible', true)
        );
    }

    public function test_cross_tenant_package_returns_404(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other', 'status' => 'active', 'plan' => 'starter']);
        $otherPackage = Package::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->actingAs($this->owner);

        $response = $this->get("/admin/packages/{$otherPackage->id}/edit");

        $response->assertStatus(404);
    }
}
