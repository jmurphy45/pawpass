<?php

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class SettingsControllerTest extends TestCase
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
            'slug' => 'settings-test',
            'status' => 'active',
            'name' => 'Test Daycare',
            'timezone' => 'America/New_York',
            'primary_color' => '#ff5500',
            'low_credit_threshold' => 2,
            'checkin_block_at_zero' => true,
            'payout_schedule' => 'monthly',
        ]);

        URL::forceRootUrl('http://settings-test.pawpass.com');

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'business_owner',
        ]);

        $this->tenant->update(['owner_user_id' => $this->owner->id]);

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

    // -------------------------------------------------------------------------
    // Business Settings — GET
    // -------------------------------------------------------------------------

    public function test_show_business_returns_tenant_fields(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())
            ->getJson('/api/admin/v1/settings/business');

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Test Daycare')
            ->assertJsonPath('data.timezone', 'America/New_York')
            ->assertJsonPath('data.primary_color', '#ff5500')
            ->assertJsonPath('data.low_credit_threshold', 2)
            ->assertJsonPath('data.checkin_block_at_zero', true)
            ->assertJsonPath('data.payout_schedule', 'monthly');
    }

    public function test_show_business_is_forbidden_for_staff(): void
    {
        $this->withHeaders($this->staffHeaders())
            ->getJson('/api/admin/v1/settings/business')
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Business Settings — PATCH
    // -------------------------------------------------------------------------

    public function test_update_business_updates_tenant_fields(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())
            ->patchJson('/api/admin/v1/settings/business', [
                'name' => 'Updated Daycare',
                'timezone' => 'America/Chicago',
                'primary_color' => '#aabbcc',
                'low_credit_threshold' => 5,
                'checkin_block_at_zero' => false,
                'payout_schedule' => 'weekly',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Daycare')
            ->assertJsonPath('data.timezone', 'America/Chicago')
            ->assertJsonPath('data.primary_color', '#aabbcc')
            ->assertJsonPath('data.low_credit_threshold', 5)
            ->assertJsonPath('data.checkin_block_at_zero', false)
            ->assertJsonPath('data.payout_schedule', 'weekly');

        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id, 'name' => 'Updated Daycare']);
    }

    public function test_update_business_partial_update(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())
            ->patchJson('/api/admin/v1/settings/business', [
                'name' => 'Partial Update',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Partial Update')
            ->assertJsonPath('data.timezone', 'America/New_York');
    }

    public function test_update_business_rejects_invalid_timezone(): void
    {
        $this->withHeaders($this->ownerHeaders())
            ->patchJson('/api/admin/v1/settings/business', ['timezone' => 'Not/ATimezone'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['timezone']);
    }

    public function test_update_business_rejects_invalid_color(): void
    {
        $this->withHeaders($this->ownerHeaders())
            ->patchJson('/api/admin/v1/settings/business', ['primary_color' => 'not-a-color'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['primary_color']);
    }

    public function test_update_business_rejects_invalid_payout_schedule(): void
    {
        $this->withHeaders($this->ownerHeaders())
            ->patchJson('/api/admin/v1/settings/business', ['payout_schedule' => 'yearly'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payout_schedule']);
    }

    public function test_update_business_is_forbidden_for_staff(): void
    {
        $this->withHeaders($this->staffHeaders())
            ->patchJson('/api/admin/v1/settings/business', ['name' => 'Hack'])
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Notification Settings — GET
    // -------------------------------------------------------------------------

    public function test_show_notifications_returns_settings(): void
    {
        \Illuminate\Support\Facades\DB::table('tenant_notification_settings')->insert([
            'tenant_id' => $this->tenant->id,
            'type' => 'credits.low',
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeaders($this->ownerHeaders())
            ->getJson('/api/admin/v1/settings/notifications');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('credits.low', $data[0]['type']);
        $this->assertTrue((bool) $data[0]['is_enabled']);
    }

    public function test_show_notifications_returns_empty_when_none(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())
            ->getJson('/api/admin/v1/settings/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_show_notifications_is_forbidden_for_staff(): void
    {
        $this->withHeaders($this->staffHeaders())
            ->getJson('/api/admin/v1/settings/notifications')
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Notification Settings — PUT
    // -------------------------------------------------------------------------

    public function test_update_notifications_upserts_settings(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())
            ->putJson('/api/admin/v1/settings/notifications', [
                'settings' => [
                    ['type' => 'credits.low', 'is_enabled' => false],
                    ['type' => 'attendance.checkin', 'is_enabled' => true],
                ],
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('tenant_notification_settings', [
            'tenant_id' => $this->tenant->id,
            'type' => 'credits.low',
            'is_enabled' => false,
        ]);
        $this->assertDatabaseHas('tenant_notification_settings', [
            'tenant_id' => $this->tenant->id,
            'type' => 'attendance.checkin',
            'is_enabled' => true,
        ]);
    }

    public function test_update_notifications_updates_existing_setting(): void
    {
        \Illuminate\Support\Facades\DB::table('tenant_notification_settings')->insert([
            'tenant_id' => $this->tenant->id,
            'type' => 'credits.low',
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeaders($this->ownerHeaders())
            ->putJson('/api/admin/v1/settings/notifications', [
                'settings' => [['type' => 'credits.low', 'is_enabled' => false]],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('tenant_notification_settings', [
            'tenant_id' => $this->tenant->id,
            'type' => 'credits.low',
            'is_enabled' => false,
        ]);
    }

    public function test_update_notifications_requires_settings_array(): void
    {
        $this->withHeaders($this->ownerHeaders())
            ->putJson('/api/admin/v1/settings/notifications', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings']);
    }

    public function test_update_notifications_is_forbidden_for_staff(): void
    {
        $this->withHeaders($this->staffHeaders())
            ->putJson('/api/admin/v1/settings/notifications', [
                'settings' => [['type' => 'credits.low', 'is_enabled' => false]],
            ])
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Staff Invite
    // -------------------------------------------------------------------------

    public function test_invite_staff_creates_pending_user(): void
    {
        $this->mock(NotificationService::class)->shouldIgnoreMissing();

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/settings/staff/invite', [
                'name' => 'Jane Staff',
                'email' => 'jane@example.com',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Jane Staff')
            ->assertJsonPath('data.email', 'jane@example.com')
            ->assertJsonPath('data.status', 'pending_invite');

        $this->assertDatabaseHas('users', [
            'tenant_id' => $this->tenant->id,
            'email' => 'jane@example.com',
            'role' => 'staff',
            'status' => 'pending_invite',
        ]);

        $user = User::where('email', 'jane@example.com')->first();
        $this->assertNotNull($user->invite_token);
        $this->assertNotNull($user->invite_expires_at);
        $this->assertTrue($user->invite_expires_at->isAfter(now()->addHours(47)));
    }

    public function test_invite_staff_dispatches_notification(): void
    {
        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('dispatch')
            ->once()
            ->with('staff.invite', $this->tenant->id, \Mockery::type('string'), \Mockery::type('array'));

        $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/settings/staff/invite', [
                'name' => 'Bob Staff',
                'email' => 'bob@example.com',
            ])
            ->assertStatus(201);
    }

    public function test_invite_staff_rejects_duplicate_email(): void
    {
        User::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'duplicate@example.com']);

        $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/settings/staff/invite', [
                'name' => 'Dupe',
                'email' => 'duplicate@example.com',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_invite_staff_is_forbidden_for_staff(): void
    {
        $this->withHeaders($this->staffHeaders())
            ->postJson('/api/admin/v1/settings/staff/invite', [
                'name' => 'New',
                'email' => 'new@example.com',
            ])
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Staff Deactivate
    // -------------------------------------------------------------------------

    public function test_deactivate_staff_sets_status_suspended(): void
    {
        $target = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
            'status' => 'active',
        ]);

        $this->withHeaders($this->ownerHeaders())
            ->deleteJson("/api/admin/v1/settings/staff/{$target->id}")
            ->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'suspended']);
    }

    public function test_deactivate_staff_returns_404_for_different_tenant(): void
    {
        $other = Tenant::factory()->create(['slug' => 'other-settings', 'status' => 'active']);
        $target = User::factory()->create(['tenant_id' => $other->id, 'role' => 'staff']);

        $this->withHeaders($this->ownerHeaders())
            ->deleteJson("/api/admin/v1/settings/staff/{$target->id}")
            ->assertStatus(404);
    }

    public function test_deactivate_staff_returns_422_for_tenant_owner(): void
    {
        $this->withHeaders($this->ownerHeaders())
            ->deleteJson("/api/admin/v1/settings/staff/{$this->owner->id}")
            ->assertStatus(422);
    }

    public function test_deactivate_staff_returns_422_for_platform_admin(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'platform_admin',
        ]);

        $this->withHeaders($this->ownerHeaders())
            ->deleteJson("/api/admin/v1/settings/staff/{$admin->id}")
            ->assertStatus(422);
    }

    public function test_deactivate_staff_is_forbidden_for_staff(): void
    {
        $target = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $this->withHeaders($this->staffHeaders())
            ->deleteJson("/api/admin/v1/settings/staff/{$target->id}")
            ->assertStatus(403);
    }
}
