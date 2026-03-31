<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Tenant;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => 'business_owner',
            'status'    => 'active',
        ]);
    }

    public function test_owner_can_view_settings(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get('/admin/settings');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Settings/Index')
            ->has('business')
            ->has('notificationSettings')
            ->has('staff')
        );
    }

    public function test_staff_cannot_access_settings(): void
    {
        $staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);

        $this->actingAs($staff);

        $response = $this->get('/admin/settings');

        $response->assertStatus(403);
    }

    public function test_owner_can_update_business_settings(): void
    {
        $this->actingAs($this->owner);

        $response = $this->patch('/admin/settings/business', [
            'name' => 'Updated Business Name',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id, 'name' => 'Updated Business Name']);
    }

    public function test_staff_invite_creates_pending_user(): void
    {
        $this->mock(NotificationService::class)->shouldIgnoreMissing();

        $this->actingAs($this->owner);

        $response = $this->post('/admin/settings/staff/invite', [
            'name'  => 'New Staff',
            'email' => 'newstaff@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'email'  => 'newstaff@example.com',
            'role'   => 'staff',
            'status' => 'pending_invite',
        ]);
    }

    public function test_owner_can_update_notification_settings(): void
    {
        $this->actingAs($this->owner);

        $response = $this->patch('/admin/settings/notifications', [
            'settings' => [
                ['type' => 'credits.low', 'is_enabled' => false],
                ['type' => 'subscription.renewed', 'is_enabled' => true],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('tenant_notification_settings', [
            'tenant_id'  => $this->tenant->id,
            'type'       => 'credits.low',
            'is_enabled' => false,
        ]);
    }

    public function test_cannot_deactivate_last_active_business_owner(): void
    {
        // $this->owner is the only active business_owner on this tenant
        $this->actingAs($this->owner);

        $response = $this->patch("/admin/settings/staff/{$this->owner->id}/deactivate");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $this->owner->id, 'status' => 'active']);
    }

    public function test_deactivate_staff_sets_status_suspended(): void
    {
        $staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);

        $this->actingAs($this->owner);

        $response = $this->patch("/admin/settings/staff/{$staff->id}/deactivate");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'status' => 'suspended']);
    }

    public function test_owner_can_update_billing_address(): void
    {
        $this->actingAs($this->owner);

        $response = $this->patch('/admin/settings/billing-address', [
            'street'      => '123 Main St',
            'city'        => 'Springfield',
            'state'       => 'IL',
            'postal_code' => '62701',
            'country'     => 'US',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $address = $this->tenant->fresh()->billing_address;
        $this->assertSame('123 Main St', $address['street']);
        $this->assertSame('Springfield', $address['city']);
        $this->assertSame('IL', $address['state']);
        $this->assertSame('62701', $address['postal_code']);
        $this->assertSame('US', $address['country']);
    }

    public function test_billing_address_requires_street_city_postal_code_country(): void
    {
        $this->actingAs($this->owner);

        $response = $this->patch('/admin/settings/billing-address', []);

        $response->assertSessionHasErrors(['street', 'city', 'postal_code', 'country']);
    }

    public function test_billing_address_rejects_invalid_country_code(): void
    {
        $this->actingAs($this->owner);

        $response = $this->patch('/admin/settings/billing-address', [
            'street'      => '123 Main St',
            'city'        => 'Springfield',
            'postal_code' => '62701',
            'country'     => 'USA',
        ]);

        $response->assertSessionHasErrors(['country']);
    }

    public function test_billing_address_is_forbidden_for_staff(): void
    {
        $staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);

        $this->actingAs($staff);

        $response = $this->patch('/admin/settings/billing-address', [
            'street'      => '123 Main St',
            'city'        => 'Springfield',
            'postal_code' => '62701',
            'country'     => 'US',
        ]);

        $response->assertStatus(403);
    }

    public function test_settings_index_includes_billing_address(): void
    {
        $this->tenant->update(['billing_address' => ['street' => '456 Oak Ave', 'city' => 'Chicago', 'state' => 'IL', 'postal_code' => '60601', 'country' => 'US']]);

        $this->actingAs($this->owner);

        $response = $this->get('/admin/settings');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Settings/Index')
            ->where('billing_address.postal_code', '60601')
        );
    }
}
