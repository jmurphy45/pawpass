<?php

namespace Tests\Feature\Web\Admin;

use App\Models\PlatformPlan;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PromotionControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        PlatformPlan::factory()->create(['slug' => 'starter', 'features' => ['manage_promotions']]);
        $this->tenant = Tenant::factory()->create([
            'slug' => 'promo-admin-test',
            'status' => 'active',
            'plan' => 'starter',
        ]);
        URL::forceRootUrl('http://promo-admin-test.pawpass.com');

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'business_owner',
        ]);

        $this->staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);
    }

    public function test_owner_can_view_promotions_index(): void
    {
        Promotion::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->owner);

        $response = $this->get('/admin/promotions');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Promotions/Index')
            ->has('promotions', 1)
        );
    }

    public function test_owner_can_create_promotion(): void
    {
        $this->actingAs($this->owner);

        $response = $this->post('/admin/promotions', [
            'name' => 'Summer Sale',
            'code' => 'SUMMER10',
            'type' => 'percentage',
            'discount_value' => 10,
        ]);

        $response->assertRedirect('/admin/promotions');
        $this->assertDatabaseHas('promotions', [
            'tenant_id' => $this->tenant->id,
            'code' => 'SUMMER10',
            'discount_value' => 10,
            'is_active' => true,
        ]);
    }

    public function test_code_is_uppercased_on_store(): void
    {
        $this->actingAs($this->owner);

        $this->post('/admin/promotions', [
            'name' => 'Test',
            'code' => 'lowercase',
            'type' => 'percentage',
            'discount_value' => 5,
        ]);

        $this->assertDatabaseHas('promotions', ['code' => 'LOWERCASE']);
    }

    public function test_staff_cannot_create_promotion(): void
    {
        $this->actingAs($this->staff);

        $response = $this->post('/admin/promotions', [
            'name' => 'Staff Promo',
            'code' => 'STAFF',
            'type' => 'percentage',
            'discount_value' => 5,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('promotions', 0);
    }

    public function test_owner_can_deactivate_promotion(): void
    {
        $promo = Promotion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->owner);

        $response = $this->patch("/admin/promotions/{$promo->id}", ['is_active' => false]);

        $response->assertRedirect();
        $this->assertFalse($promo->fresh()->is_active);
    }

    public function test_owner_can_delete_promotion(): void
    {
        $promo = Promotion::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->owner);

        $response = $this->delete("/admin/promotions/{$promo->id}");

        $response->assertRedirect('/admin/promotions');
        $this->assertSoftDeleted('promotions', ['id' => $promo->id]);
    }

    public function test_percentage_discount_over_100_is_rejected(): void
    {
        $this->actingAs($this->owner);

        $response = $this->post('/admin/promotions', [
            'name' => 'Bad',
            'code' => 'BAD',
            'type' => 'percentage',
            'discount_value' => 110,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('discount_value');
    }

    public function test_create_records_promo_created_event(): void
    {
        $this->actingAs($this->owner);

        $this->post('/admin/promotions', [
            'name' => 'Event Test',
            'code' => 'EVTTEST',
            'type' => 'percentage',
            'discount_value' => 15,
        ]);

        $this->assertDatabaseHas('tenant_events', [
            'tenant_id' => $this->tenant->id,
            'event_type' => 'promo_created',
        ]);
    }
}
