<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Laravel\Pennant\Feature;
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
            'role' => 'business_owner',
            'status' => 'active',
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
            'status' => 'active',
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
            'name' => 'New Staff',
            'email' => 'newstaff@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'email' => 'newstaff@example.com',
            'role' => 'staff',
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
            'tenant_id' => $this->tenant->id,
            'type' => 'credits.low',
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
            'status' => 'active',
        ]);

        $this->actingAs($this->owner);

        $response = $this->patch("/admin/settings/staff/{$staff->id}/deactivate");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'status' => 'suspended']);
    }

    public function test_reinviting_a_suspended_staff_member_reuses_the_record(): void
    {
        $this->mock(NotificationService::class)->shouldIgnoreMissing();

        $suspended = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'reinstated@example.com',
            'status' => 'suspended',
            'invite_token' => 'old-token',
        ]);

        $userCountBefore = User::count();

        $this->actingAs($this->owner);

        $response = $this->post('/admin/settings/staff/invite', [
            'name' => 'Reinstated Staff',
            'email' => 'reinstated@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame($userCountBefore, User::count());
        $this->assertDatabaseHas('users', [
            'id' => $suspended->id,
            'status' => 'pending_invite',
            'name' => 'Reinstated Staff',
        ]);
        $this->assertDatabaseMissing('users', ['invite_token' => 'old-token']);
    }

    public function test_reinviting_a_pending_invite_staff_member_refreshes_the_token(): void
    {
        $this->mock(NotificationService::class)->shouldIgnoreMissing();

        $pending = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'pending@example.com',
            'status' => 'pending_invite',
            'invite_token' => 'stale-token',
            'invite_expires_at' => now()->subDay(),
        ]);

        $userCountBefore = User::count();

        $this->actingAs($this->owner);

        $response = $this->post('/admin/settings/staff/invite', [
            'name' => 'Pending Staff',
            'email' => 'pending@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame($userCountBefore, User::count());
        $this->assertDatabaseMissing('users', ['invite_token' => 'stale-token']);
        $fresh = $pending->fresh();
        $this->assertSame('pending_invite', $fresh->status);
        $this->assertTrue($fresh->invite_expires_at->isFuture());
    }

    public function test_inviting_an_already_active_user_returns_validation_error(): void
    {
        $active = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'active@example.com',
            'status' => 'active',
        ]);

        $userCountBefore = User::count();

        $this->actingAs($this->owner);

        $response = $this->post('/admin/settings/staff/invite', [
            'name' => 'Active Staff',
            'email' => 'active@example.com',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertSame($userCountBefore, User::count());
    }

    public function test_owner_can_update_billing_address(): void
    {
        $this->actingAs($this->owner);

        $response = $this->patch('/admin/settings/billing-address', [
            'street' => '123 Main St',
            'city' => 'Springfield',
            'state' => 'IL',
            'postal_code' => '62701',
            'country' => 'US',
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
            'street' => '123 Main St',
            'city' => 'Springfield',
            'postal_code' => '62701',
            'country' => 'USA',
        ]);

        $response->assertSessionHasErrors(['country']);
    }

    public function test_billing_address_is_forbidden_for_staff(): void
    {
        $staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        $this->actingAs($staff);

        $response = $this->patch('/admin/settings/billing-address', [
            'street' => '123 Main St',
            'city' => 'Springfield',
            'postal_code' => '62701',
            'country' => 'US',
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

    public function test_settings_index_includes_packages_and_auto_charge_fields(): void
    {
        Queue::fake();

        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'one_time',
        ]);

        $this->actingAs($this->owner);

        $response = $this->get('/admin/settings');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Settings/Index')
            ->has('packages')
            ->has('can_auto_replenish')
            ->where('business.auto_charge_at_zero_package_id', null)
        );
    }

    public function test_can_auto_replenish_is_true_when_feature_active_for_tenant(): void
    {
        Feature::for($this->tenant)->activate('auto_replenish');
        $this->actingAs($this->owner);

        $response = $this->get('/admin/settings');

        $response->assertInertia(fn ($page) => $page
            ->where('can_auto_replenish', true)
        );
    }

    public function test_can_auto_replenish_is_false_when_feature_inactive_for_tenant(): void
    {
        Feature::for($this->tenant)->deactivate('auto_replenish');
        $this->actingAs($this->owner);

        $response = $this->get('/admin/settings');

        $response->assertInertia(fn ($page) => $page
            ->where('can_auto_replenish', false)
        );
    }

    public function test_update_business_saves_auto_charge_package_id(): void
    {
        Queue::fake();

        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'one_time',
        ]);

        $this->actingAs($this->owner);

        $response = $this->patch('/admin/settings/business', [
            'auto_charge_at_zero_package_id' => $package->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame($package->id, $this->tenant->fresh()->auto_charge_at_zero_package_id);
    }

    public function test_update_business_clears_auto_charge_package_id_when_null(): void
    {
        Queue::fake();

        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'one_time',
        ]);

        $this->tenant->update(['auto_charge_at_zero_package_id' => $package->id]);

        $this->actingAs($this->owner);

        $response = $this->patch('/admin/settings/business', [
            'auto_charge_at_zero_package_id' => null,
        ]);

        $response->assertRedirect();
        $this->assertNull($this->tenant->fresh()->auto_charge_at_zero_package_id);
    }

    public function test_update_business_rejects_invalid_package_id(): void
    {
        $this->actingAs($this->owner);

        $response = $this->patch('/admin/settings/business', [
            'auto_charge_at_zero_package_id' => 'nonexistent_pkg_id',
        ]);

        $response->assertSessionHasErrors(['auto_charge_at_zero_package_id']);
    }

    // ── Home page settings ──────────────────────────────────────────────────

    private function validHomePagePayload(array $overrides = []): array
    {
        $defaults = \App\Models\TenantSettings::homePageDefaults();

        return array_merge([
            'hero_headline' => $defaults['hero_headline'],
            'trust_badges' => $defaults['trust_badges'],
            'why_section_headline' => $defaults['why_section_headline'],
            'why_cards' => $defaults['why_cards'],
            'footer_cta_headline' => $defaults['footer_cta_headline'],
        ], $overrides);
    }

    public function test_settings_index_passes_home_page_to_inertia(): void
    {
        $this->actingAs($this->owner);

        $this->get('/admin/settings')
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Settings/Index')
                ->has('home_page')
                ->where('home_page.hero_headline', "Your dog's home away from home.")
            );
    }

    public function test_owner_can_update_home_page_settings(): void
    {
        $this->actingAs($this->owner);

        $payload = $this->validHomePagePayload(['hero_headline' => 'Woof woof welcome!']);

        $this->patch('/admin/settings/home-page', $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $meta = \App\Models\TenantSettings::allTenants()
            ->where('tenant_id', $this->tenant->id)
            ->value('meta');

        $this->assertEquals('Woof woof welcome!', $meta['home_page']['hero_headline']);
    }

    public function test_staff_cannot_update_home_page_settings(): void
    {
        $staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);
        $this->actingAs($staff);

        $this->patch('/admin/settings/home-page', $this->validHomePagePayload())
            ->assertStatus(403);
    }

    public function test_home_page_update_validates_badge_count(): void
    {
        $this->actingAs($this->owner);

        $payload = $this->validHomePagePayload([
            'trust_badges' => array_slice(\App\Models\TenantSettings::homePageDefaults()['trust_badges'], 0, 5),
        ]);

        $this->patch('/admin/settings/home-page', $payload)
            ->assertSessionHasErrors(['trust_badges']);
    }

    public function test_home_page_update_validates_icon_allowlist(): void
    {
        $this->actingAs($this->owner);

        $cards = \App\Models\TenantSettings::homePageDefaults()['why_cards'];
        $cards[0]['icon'] = 'trash';
        $payload = $this->validHomePagePayload(['why_cards' => $cards]);

        $this->patch('/admin/settings/home-page', $payload)
            ->assertSessionHasErrors(['why_cards.0.icon']);
    }

    public function test_home_page_update_validates_hero_max_length(): void
    {
        $this->actingAs($this->owner);

        $payload = $this->validHomePagePayload(['hero_headline' => str_repeat('a', 121)]);

        $this->patch('/admin/settings/home-page', $payload)
            ->assertSessionHasErrors(['hero_headline']);
    }

    public function test_home_page_update_creates_settings_row_if_none_exists(): void
    {
        $this->actingAs($this->owner);

        $this->assertDatabaseMissing('tenant_settings', ['tenant_id' => $this->tenant->id]);

        $this->patch('/admin/settings/home-page', $this->validHomePagePayload())
            ->assertRedirect();

        $this->assertDatabaseHas('tenant_settings', ['tenant_id' => $this->tenant->id]);
    }

    public function test_home_page_update_preserves_other_meta_keys(): void
    {
        $this->actingAs($this->owner);

        \App\Models\TenantSettings::create([
            'tenant_id' => $this->tenant->id,
            'meta' => ['other_key' => 'preserved'],
        ]);

        $this->patch('/admin/settings/home-page', $this->validHomePagePayload())
            ->assertRedirect();

        $meta = \App\Models\TenantSettings::allTenants()
            ->where('tenant_id', $this->tenant->id)
            ->value('meta');

        $this->assertEquals('preserved', $meta['other_key']);
        $this->assertArrayHasKey('home_page', $meta);
    }
}
