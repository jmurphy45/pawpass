<?php

namespace Tests\Feature\Admin;

use App\Models\PushSubscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class PushSubscriptionTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'adminpushtest', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://adminpushtest.pawpass.com');

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'business_owner',
        ]);
        $this->staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);
    }

    public function test_owner_can_subscribe(): void
    {
        $token = $this->jwtFor($this->owner);

        $this->postJson('/api/admin/v1/push-subscriptions', [
            'endpoint' => 'https://push.example.com/owner1',
            'p256dh' => 'dGVzdGtleQ==',
            'auth' => 'dGVzdGF1dGg=',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $this->owner->id,
            'endpoint' => 'https://push.example.com/owner1',
        ]);
    }

    public function test_staff_can_subscribe(): void
    {
        $token = $this->jwtFor($this->staff);

        $this->postJson('/api/admin/v1/push-subscriptions', [
            'endpoint' => 'https://push.example.com/staff1',
            'p256dh' => 'dGVzdGtleQ==',
            'auth' => 'dGVzdGF1dGg=',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $this->staff->id,
        ]);
    }

    public function test_subscribe_upserts_existing_endpoint(): void
    {
        $token = $this->jwtFor($this->owner);

        PushSubscription::create([
            'user_id' => $this->owner->id,
            'tenant_id' => $this->tenant->id,
            'endpoint' => 'https://push.example.com/owner1',
            'p256dh' => 'oldkey',
            'auth_token' => 'oldauth',
        ]);

        $this->postJson('/api/admin/v1/push-subscriptions', [
            'endpoint' => 'https://push.example.com/owner1',
            'p256dh' => 'newkey',
            'auth' => 'newauth',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200);

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', ['p256dh' => 'newkey']);
    }

    public function test_subscribe_requires_authentication(): void
    {
        $this->postJson('/api/admin/v1/push-subscriptions', [
            'endpoint' => 'https://push.example.com/sub1',
            'p256dh' => 'key',
            'auth' => 'auth',
        ])->assertStatus(401);
    }

    public function test_subscribe_validates_required_fields(): void
    {
        $token = $this->jwtFor($this->owner);

        $this->postJson('/api/admin/v1/push-subscriptions', [], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['endpoint', 'p256dh', 'auth']);
    }

    public function test_owner_can_unsubscribe(): void
    {
        $token = $this->jwtFor($this->owner);

        PushSubscription::create([
            'user_id' => $this->owner->id,
            'tenant_id' => $this->tenant->id,
            'endpoint' => 'https://push.example.com/owner1',
            'p256dh' => 'key',
            'auth_token' => 'auth',
        ]);

        $this->deleteJson('/api/admin/v1/push-subscriptions', [
            'endpoint' => 'https://push.example.com/owner1',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200);

        $this->assertDatabaseMissing('push_subscriptions', [
            'user_id' => $this->owner->id,
        ]);
    }
}
